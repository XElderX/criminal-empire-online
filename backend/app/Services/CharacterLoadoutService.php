<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class CharacterLoadoutService
{
    public function boss(array $user): array
    {
        return $this->forCharacter($user, 'boss', 0);
    }

    public function crew(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    member.*,
                    npc.first_name,
                    npc.last_name,
                    npc.nickname,
                    npc.gender,
                    npc.age,
                    npc.biography,
                    npc.background,
                    npc.occupation,
                    npc.personal_cash,
                    npc.criminal_reputation,
                    npc.portrait_set_key,
                    npc.portrait_stage_cache,
                    npc.portrait_focal_x,
                    npc.portrait_focal_y,
                    territory.name AS territory_name
                FROM player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                LEFT JOIN territories territory ON territory.id = npc.home_territory_id
                WHERE member.user_id = ? AND member.status != 'dismissed'
                ORDER BY member.status, member.level DESC, member.id ASC
            SQL
        );
        $statement->execute([$user['id']]);
        $members = [];
        $presenter = new CrewPresentationService();
        foreach ($statement->fetchAll() as $member) {
            $presented = $presenter->present($member);
            $members[] = array_merge($presented, ['loadout' => $this->forCharacter($user, 'crew', (int) $member['id'])]);
        }
        return ['data' => $members];
    }

    public function forCharacter(array $user, string $characterType, int $characterId): array
    {
        (new EquipmentValidationService())->validateCrewMember($user, $characterType, $characterId);
        $equipped = $this->equippedItems((int) $user['id'], $characterType, $characterId);
        $carried = $this->carriedItems((int) $user['id'], $characterType, $characterId);
        $capacity = $this->carryCapacity($equipped);
        $used = array_reduce($carried, static fn (float $sum, array $item): float => $sum + ((float) ($item['carry_units_each'] ?? $item['carry_units'] ?? 1) * (int) ($item['quantity'] ?? 1)), 0.0);
        $scores = (new LoadoutScoreService())->score($equipped, $carried);
        $warnings = (new LoadoutPenaltyService())->warnings($scores, $used, $capacity);

        return [
            'character_type' => $characterType,
            'character_id' => $characterId,
            'slots' => (new EquipmentSlotService())->slots(),
            'equipped' => $equipped,
            'carried' => $carried,
            'carry_capacity_units' => $capacity,
            'used_carry_units' => $used,
            'scores' => $scores,
            'warnings' => $warnings,
        ];
    }

    public function equip(array $user, string $characterType, int $characterId, int $assetId, string $slot, string $assetType = 'item'): array
    {
        if (!in_array($assetType, ['item', 'weapon'], true)) {
            throw new RuntimeException('Unsupported loadout asset type.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            (new EquipmentValidationService())->validateCrewMember($user, $characterType, $characterId);
            $asset = $assetType === 'weapon'
                ? $this->lockWeapon((int) $user['id'], $assetId)
                : (new EquipmentValidationService())->lockItem((int) $user['id'], $assetId);

            if ($assetType === 'item') {
                if ((int) ($asset['is_equippable'] ?? 1) !== 1) {
                    throw new RuntimeException('This item cannot be equipped.');
                }
                if (($asset['durability'] ?? null) !== null && (int) $asset['durability'] <= 0) {
                    throw new RuntimeException('Broken item cannot be equipped.');
                }
                if (!(new EquipmentSlotService())->itemCanUseSlot($asset, $slot)) {
                    throw new RuntimeException('Item does not match that equipment slot.');
                }
                $definitionId = (int) $asset['item_definition_id'];
                $ownedQuantity = (int) ($asset['quantity'] ?? 0);
                $carriedForCharacter = $this->carriedQuantityForCharacter((int) $user['id'], $characterType, $characterId, 'item', $definitionId);
            } else {
                $slot = $this->normalizeWeaponSlot($asset, $slot);
                $definitionId = (int) $asset['weapon_id'];
                $ownedQuantity = (int) ($asset['quantity'] ?? 0);
                $carriedForCharacter = 0;
            }

            $equippedQuantity = $this->equippedQuantity((int) $user['id'], $assetType, $definitionId);
            $carriedQuantity = $this->carriedQuantity((int) $user['id'], $assetType, $definitionId);
            $alreadyThisSlot = $this->equipmentInSlot((int) $user['id'], $characterType, $characterId, $slot);
            if ($alreadyThisSlot && $alreadyThisSlot['asset_type'] === $assetType && (int) $alreadyThisSlot['asset_id'] === $definitionId) {
                $pdo->commit();
                return ['message' => "{$asset['name']} is already equipped in {$slot}.", 'loadout' => $this->forCharacter($user, $characterType, $characterId)];
            }

            $available = $ownedQuantity - $equippedQuantity - $carriedQuantity + $carriedForCharacter;
            if ($alreadyThisSlot) {
                $pdo->prepare('DELETE FROM crew_equipment WHERE id = ? AND user_id = ?')->execute([$alreadyThisSlot['id'], $user['id']]);
                $available += 1;
            }
            if ($available < 1) {
                throw new RuntimeException('No available copy of this gear is left to equip.');
            }

            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO crew_equipment (user_id, gang_member_id, asset_type, asset_id, equipment_slot, durability, equipped_at)
                    VALUES (?, ?, ?, ?, ?, COALESCE(?, 100), NOW())
                SQL
            )->execute([
                $user['id'],
                $characterType === 'crew' ? $characterId : null,
                $assetType,
                $definitionId,
                $slot,
                $asset['durability'] ?? $asset['base_durability'] ?? 100,
            ]);

            if ($assetType === 'item') {
                if ($carriedForCharacter > 0) {
                    $this->removeOneCarriedItem((int) $user['id'], $characterType, $characterId, $definitionId);
                }
                $pdo->prepare('UPDATE user_items SET current_location_type = \'equipped\', holder_type = ?, holder_id = ?, equipped_slot = ?, updated_at = NOW() WHERE user_id = ? AND item_definition_id = ?')
                    ->execute([$characterType, $characterId, $slot, $user['id'], $definitionId]);
            }

            (new InventoryLogService())->record((int) $user['id'], 'equip', "Equipped {$asset['name']} to {$characterType} {$characterId} slot {$slot}.", [
                'character_type' => $characterType,
                'character_id' => $characterId,
                'asset_type' => $assetType,
                'asset_id' => $definitionId,
                'item_key' => $asset['code'] ?? $asset['name'],
                'to_holder' => $slot,
            ]);
            $pdo->commit();
            return ['message' => "{$asset['name']} equipped to {$slot}.", 'loadout' => $this->forCharacter($user, $characterType, $characterId)];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function unequip(array $user, string $characterType, int $characterId, string $slot): array
    {
        (new EquipmentValidationService())->validateCrewMember($user, $characterType, $characterId);
        if ($characterType === 'crew') {
            Database::pdo()->prepare('DELETE FROM crew_equipment WHERE user_id = ? AND gang_member_id = ? AND equipment_slot = ?')
                ->execute([$user['id'], $characterId, $slot]);
        } else {
            Database::pdo()->prepare('DELETE FROM crew_equipment WHERE user_id = ? AND gang_member_id IS NULL AND equipment_slot = ?')
                ->execute([$user['id'], $slot]);
        }
        Database::pdo()->prepare('UPDATE user_items SET current_location_type = \'owned\', holder_type = \'user\', holder_id = NULL, equipped_slot = NULL, updated_at = NOW() WHERE user_id = ? AND holder_type = ? AND holder_id = ? AND equipped_slot = ?')
            ->execute([$user['id'], $characterType, $characterId, $slot]);
        (new InventoryLogService())->record((int) $user['id'], 'unequip', "Unequipped {$slot} from {$characterType} {$characterId}.", [
            'character_type' => $characterType, 'character_id' => $characterId, 'from_holder' => $slot,
        ]);
        return ['message' => 'Item unequipped.', 'loadout' => $this->forCharacter($user, $characterType, $characterId)];
    }

    public function carry(array $user, string $characterType, int $characterId, int $itemId, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 5) throw new RuntimeException('Carry quantity must be between 1 and 5.');
        (new EquipmentValidationService())->validateCrewMember($user, $characterType, $characterId);
        $item = (new EquipmentValidationService())->lockItem((int) $user['id'], $itemId);
        if ((int) ($item['is_carryable'] ?? 1) !== 1) throw new RuntimeException('This item cannot be carried.');
        if (!$this->itemCanBeCarriedAsTaskGear($item)) {
            throw new RuntimeException('This item belongs in an equipment slot, not carried inventory.');
        }
        $available = (int) ($item['quantity'] ?? 0) - $this->equippedQuantity((int) $user['id'], 'item', (int) $item['item_definition_id']) - $this->carriedQuantity((int) $user['id'], 'item', (int) $item['item_definition_id']);
        if ($available < $quantity) throw new RuntimeException('No available copy of this item is left to carry.');
        $loadout = $this->forCharacter($user, $characterType, $characterId);
        $additional = (float) ($item['carry_units'] ?? 1) * $quantity;
        if ((float) $loadout['used_carry_units'] + $additional > (float) $loadout['carry_capacity_units']) {
            throw new RuntimeException('Carry capacity exceeded.');
        }
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO character_carry_items (user_id, character_type, character_id, asset_type, asset_id, quantity, carry_units_each, carried_slot, created_at, updated_at)
                VALUES (?, ?, ?, 'item', ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = NOW()
            SQL
        )->execute([$user['id'], $characterType, $characterId, $item['item_definition_id'], $quantity, $item['carry_units'] ?? 1, 'carry']);
        Database::pdo()->prepare('UPDATE user_items SET current_location_type = \'carried\', holder_type = ?, holder_id = ?, carried_slot = \'carry\', updated_at = NOW() WHERE user_id = ? AND item_definition_id = ?')
            ->execute([$characterType, $characterId, $user['id'], $item['item_definition_id']]);
        (new InventoryLogService())->record((int) $user['id'], 'carry', "Assigned {$item['name']} to carried inventory.", [
            'character_type' => $characterType, 'character_id' => $characterId, 'asset_type' => 'item', 'asset_id' => $item['item_definition_id'], 'quantity' => $quantity,
        ]);
        return ['message' => "{$item['name']} carried by {$characterType} {$characterId}.", 'loadout' => $this->forCharacter($user, $characterType, $characterId)];
    }

    public function store(array $user, string $characterType, int $characterId, int $itemId): array
    {
        (new EquipmentValidationService())->validateCrewMember($user, $characterType, $characterId);
        Database::pdo()->prepare('DELETE FROM character_carry_items WHERE user_id = ? AND character_type = ? AND character_id = ? AND asset_type = \'item\' AND asset_id = ?')
            ->execute([$user['id'], $characterType, $characterId, $itemId]);
        Database::pdo()->prepare('UPDATE user_items SET current_location_type = \'owned\', holder_type = \'user\', holder_id = NULL, carried_slot = NULL, updated_at = NOW() WHERE user_id = ? AND item_definition_id = ?')
            ->execute([$user['id'], $itemId]);
        return ['message' => 'Item returned to owned inventory.', 'loadout' => $this->forCharacter($user, $characterType, $characterId)];
    }

    private function equippedItems(int $userId, string $characterType, int $characterId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    equipment.id AS equipment_id,
                    equipment.asset_type,
                    equipment.equipment_slot AS equipped_slot,
                    COALESCE(item.id, weapon.id) AS id,
                    COALESCE(item.name, weapon.name) AS name,
                    COALESCE(item.description, weapon.class) AS description,
                    COALESCE(item.category, weapon.class) AS category,
                    weapon.class AS class,
                    COALESCE(item.effects, weapon.effects) AS effects,
                    item.item_effects,
                    item.allowed_slots,
                    item.item_tags,
                    item.size_class,
                    item.carry_units,
                    COALESCE(item.legality, CASE WHEN COALESCE(weapon.illegal, 0) = 1 THEN 'illegal' ELSE 'legal' END) AS legality,
                    COALESCE(item.visible_illegal, weapon.illegal, 0) AS visible_illegal
                FROM crew_equipment equipment
                LEFT JOIN item_definitions item ON equipment.asset_type = 'item' AND item.id = equipment.asset_id
                LEFT JOIN weapons weapon ON equipment.asset_type = 'weapon' AND weapon.id = equipment.asset_id
                WHERE equipment.user_id = ?
                  AND (
                    (? = 'crew' AND equipment.gang_member_id = ?)
                    OR (? = 'boss' AND equipment.gang_member_id IS NULL)
                  )
                ORDER BY equipment.equipment_slot
            SQL
        );
        $statement->execute([$userId, $characterType, $characterId, $characterType]);
        return array_map([$this, 'normalizeAssetRow'], $statement->fetchAll());
    }

    private function carriedItems(int $userId, string $characterType, int $characterId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT carry.quantity, carry.carry_units_each, carry.asset_type, item.*
                FROM character_carry_items carry
                JOIN item_definitions item ON item.id = carry.asset_id
                WHERE carry.user_id = ? AND carry.character_type = ? AND carry.character_id = ? AND carry.asset_type = 'item'
                ORDER BY item.category, item.name
            SQL
        );
        $statement->execute([$userId, $characterType, $characterId]);
        return array_map([$this, 'normalizeAssetRow'], $statement->fetchAll());
    }

    private function carryCapacity(array $equippedItems): float
    {
        $capacity = 5.0;
        foreach ($equippedItems as $item) {
            $effects = (new ItemEffectService())->effectsForItem($item);
            $capacity += (float) ($effects['carry_capacity_bonus'] ?? 0);
        }
        return min(10.0, $capacity);
    }

    private function lockWeapon(int $userId, int $weaponId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT inventory.weapon_id, inventory.quantity, weapon.*
                FROM user_weapons inventory
                JOIN weapons weapon ON weapon.id = inventory.weapon_id
                WHERE inventory.user_id = ? AND weapon.id = ? AND inventory.quantity > 0
                LIMIT 1 FOR UPDATE
            SQL
        );
        $statement->execute([$userId, $weaponId]);
        $weapon = $statement->fetch();
        if (!$weapon) {
            throw new RuntimeException('Player cannot equip another player\'s weapon or an unowned weapon.');
        }
        return $weapon;
    }

    private function normalizeWeaponSlot(array $weapon, string $slot): string
    {
        $slot = trim($slot);
        $class = strtolower((string) ($weapon['class'] ?? ''));
        $legacy = strtolower((string) ($weapon['equipment_slot'] ?? ''));
        if ($slot === '' || $slot === 'weapon') {
            if (str_contains($class, 'pistol') || str_contains($class, 'revolver') || $legacy === 'sidearm') return 'sidearm';
            if (str_contains($class, 'knife') || str_contains($class, 'melee') || str_contains($class, 'baton')) return 'melee';
            return 'primary_weapon';
        }
        $allowed = ['primary_weapon', 'sidearm', 'melee'];
        if (!in_array($slot, $allowed, true)) {
            throw new RuntimeException('Weapon does not match that equipment slot.');
        }
        return $slot;
    }

    private function equipmentInSlot(int $userId, string $characterType, int $characterId, string $slot): ?array
    {
        if ($characterType === 'crew') {
            $statement = Database::pdo()->prepare('SELECT * FROM crew_equipment WHERE user_id = ? AND gang_member_id = ? AND equipment_slot = ? FOR UPDATE');
            $statement->execute([$userId, $characterId, $slot]);
        } else {
            $statement = Database::pdo()->prepare('SELECT * FROM crew_equipment WHERE user_id = ? AND gang_member_id IS NULL AND equipment_slot = ? FOR UPDATE');
            $statement->execute([$userId, $slot]);
        }
        $row = $statement->fetch();
        return $row ?: null;
    }

    private function equippedQuantity(int $userId, string $assetType, int $assetId): int
    {
        $statement = Database::pdo()->prepare('SELECT COUNT(*) FROM crew_equipment WHERE user_id = ? AND asset_type = ? AND asset_id = ?');
        $statement->execute([$userId, $assetType, $assetId]);
        return (int) $statement->fetchColumn();
    }

    private function carriedQuantity(int $userId, string $assetType, int $assetId): int
    {
        $statement = Database::pdo()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM character_carry_items WHERE user_id = ? AND asset_type = ? AND asset_id = ?');
        $statement->execute([$userId, $assetType, $assetId]);
        return (int) $statement->fetchColumn();
    }

    private function carriedQuantityForCharacter(int $userId, string $characterType, int $characterId, string $assetType, int $assetId): int
    {
        $statement = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(quantity), 0) FROM character_carry_items WHERE user_id = ? AND character_type = ? AND character_id = ? AND asset_type = ? AND asset_id = ?'
        );
        $statement->execute([$userId, $characterType, $characterId, $assetType, $assetId]);

        return (int) $statement->fetchColumn();
    }

    private function removeOneCarriedItem(int $userId, string $characterType, int $characterId, int $assetId): void
    {
        $statement = Database::pdo()->prepare(
            'SELECT id, quantity FROM character_carry_items WHERE user_id = ? AND character_type = ? AND character_id = ? AND asset_type = \'item\' AND asset_id = ? LIMIT 1 FOR UPDATE'
        );
        $statement->execute([$userId, $characterType, $characterId, $assetId]);
        $row = $statement->fetch();

        if (!$row) {
            return;
        }

        if ((int) $row['quantity'] <= 1) {
            Database::pdo()->prepare('DELETE FROM character_carry_items WHERE id = ?')->execute([(int) $row['id']]);
            return;
        }

        Database::pdo()->prepare('UPDATE character_carry_items SET quantity = quantity - 1, updated_at = NOW() WHERE id = ?')
            ->execute([(int) $row['id']]);
    }

    private function itemCanBeCarriedAsTaskGear(array $item): bool
    {
        $category = strtolower((string) ($item['category'] ?? ''));
        $tags = $this->decode($item['item_tags'] ?? []);
        $effects = array_merge($this->decode($item['effects'] ?? []), $this->decode($item['item_effects'] ?? []));

        if ((int) ($item['is_storage_only'] ?? 0) === 1) {
            return false;
        }

        if (in_array($category, ['tool', 'utility', 'stolen_good', 'vehicle_part', 'production_supply', 'general'], true)) {
            return true;
        }

        foreach (['medical', 'event_unlock', 'contact_safety', 'low_light', 'task_item', 'entry_tool', 'stealth_entry', 'vehicle_crime'] as $tag) {
            if (in_array($tag, $tags, true)) {
                return true;
            }
        }

        foreach (['first_aid_event_unlock', 'contact_exposure_reduction', 'vehicle_crime_bonus', 'forced_entry_bonus'] as $effectKey) {
            if (array_key_exists($effectKey, $effects)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAssetRow(array $row): array
    {
        foreach (['effects', 'item_effects', 'allowed_slots', 'item_tags'] as $key) {
            if (isset($row[$key])) {
                $row[$key] = $this->decode($row[$key]);
            }
        }
        if (isset($row['carry_units_each']) && !isset($row['carry_units'])) {
            $row['carry_units'] = $row['carry_units_each'];
        }
        return $row;
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
