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
                SELECT member.*, npc.first_name, npc.last_name, npc.nickname
                FROM player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                WHERE member.user_id = ? AND member.status != 'dismissed'
                ORDER BY member.status, member.level DESC, member.id ASC
            SQL
        );
        $statement->execute([$user['id']]);
        $members = [];
        foreach ($statement->fetchAll() as $member) {
            $members[] = array_merge($member, ['loadout' => $this->forCharacter($user, 'crew', (int) $member['id'])]);
        }
        return ['data' => $members];
    }

    public function forCharacter(array $user, string $characterType, int $characterId): array
    {
        (new EquipmentValidationService())->validateCrewMember($user, $characterType, $characterId);
        $equipped = $this->equippedItems((int) $user['id'], $characterType, $characterId);
        $carried = $this->carriedItems((int) $user['id'], $characterType, $characterId);
        $capacity = $this->carryCapacity($equipped);
        $used = array_reduce($carried, static fn (float $sum, array $item): float => $sum + ((float) ($item['carry_units'] ?? 1) * (int) ($item['quantity'] ?? 1)), 0.0);
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

    public function equip(array $user, string $characterType, int $characterId, int $itemId, string $slot): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            (new EquipmentValidationService())->validateCrewMember($user, $characterType, $characterId);
            $item = (new EquipmentValidationService())->lockItem((int) $user['id'], $itemId);
            if ((int) ($item['is_equippable'] ?? 1) !== 1) {
                throw new RuntimeException('This item cannot be equipped.');
            }
            if (($item['durability'] ?? null) !== null && (int) $item['durability'] <= 0) {
                throw new RuntimeException('Broken item cannot be equipped.');
            }
            if (!(new EquipmentSlotService())->itemCanUseSlot($item, $slot)) {
                throw new RuntimeException('Item does not match that equipment slot.');
            }
            $assetId = (int) $item['item_definition_id'];
            $taken = $pdo->prepare('SELECT id FROM crew_equipment WHERE user_id = ? AND asset_type = \'item\' AND asset_id = ? LIMIT 1');
            $taken->execute([$user['id'], $assetId]);
            if ($taken->fetch()) {
                throw new RuntimeException('One item cannot be equipped by multiple characters or in two slots.');
            }

            if ($characterType === 'crew') {
                $pdo->prepare(
                    <<<'SQL'
                        INSERT INTO crew_equipment (user_id, gang_member_id, asset_type, asset_id, equipment_slot, durability, equipped_at)
                        VALUES (?, ?, 'item', ?, ?, COALESCE(?, 100), NOW())
                        ON DUPLICATE KEY UPDATE asset_id = VALUES(asset_id), durability = VALUES(durability), equipped_at = NOW()
                    SQL
                )->execute([$user['id'], $characterId, $assetId, $slot, $item['durability'] ?? 100]);
            }

            $pdo->prepare('UPDATE user_items SET current_location_type = \'equipped\', holder_type = ?, holder_id = ?, equipped_slot = ?, updated_at = NOW() WHERE user_id = ? AND item_definition_id = ?')
                ->execute([$characterType, $characterId, $slot, $user['id'], $assetId]);

            (new InventoryLogService())->record((int) $user['id'], 'equip', "Equipped {$item['name']} to {$characterType} {$characterId} slot {$slot}.", [
                'character_type' => $characterType, 'character_id' => $characterId, 'asset_type' => 'item', 'asset_id' => $assetId, 'item_key' => $item['code'], 'to_holder' => $slot,
            ]);
            $pdo->commit();
            return ['message' => 'Item equipped.', 'loadout' => $this->forCharacter($user, $characterType, $characterId)];
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
        return ['message' => 'Item carried.', 'loadout' => $this->forCharacter($user, $characterType, $characterId)];
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
        if ($characterType !== 'crew') return [];
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT equipment.id AS equipment_id, equipment.equipment_slot AS equipped_slot, item.*
                FROM crew_equipment equipment
                JOIN item_definitions item ON item.id = equipment.asset_id
                WHERE equipment.user_id = ? AND equipment.gang_member_id = ? AND equipment.asset_type = 'item'
                ORDER BY equipment.equipment_slot
            SQL
        );
        $statement->execute([$userId, $characterId]);
        return $statement->fetchAll();
    }

    private function carriedItems(int $userId, string $characterType, int $characterId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT carry.quantity, carry.carry_units_each, item.*
                FROM character_carry_items carry
                JOIN item_definitions item ON item.id = carry.asset_id
                WHERE carry.user_id = ? AND carry.character_type = ? AND carry.character_id = ? AND carry.asset_type = 'item'
                ORDER BY item.category, item.name
            SQL
        );
        $statement->execute([$userId, $characterType, $characterId]);
        return $statement->fetchAll();
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
}
