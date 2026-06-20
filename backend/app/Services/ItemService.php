<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class ItemService
{
    public function shop(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    item.*,
                    COALESCE(inventory.quantity, 0) AS owned_quantity,
                    (
                        SELECT COUNT(*)
                        FROM crew_equipment equipment
                        WHERE equipment.user_id = ?
                          AND equipment.asset_type = 'item'
                          AND equipment.asset_id = item.id
                    ) AS equipped_quantity
                FROM item_definitions item
                LEFT JOIN user_items inventory
                    ON inventory.item_definition_id = item.id
                    AND inventory.user_id = ?
                WHERE item.active = 1
                  AND item.price > 0
                ORDER BY item.price, item.name
            SQL
        );

        $statement->execute([$user['id'], $user['id']]);
        $items = $statement->fetchAll();

        foreach ($items as &$item) {
            $item['effects'] = $this->decodeJson($item['effects']);
            $item['requirements'] = $this->decodeJson($item['requirements']);
            $item['can_buy'] = $this->requirementsMet($user, $item['requirements'])
                && (int) $user['cash'] >= (int) $item['price'];
        }

        return $items;
    }

    public function buy(array $user, int $itemId, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 100) {
            throw new RuntimeException('Quantity must be between 1 and 100.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $itemStatement = $pdo->prepare(
                'SELECT * FROM item_definitions WHERE id = ? AND active = 1 FOR UPDATE'
            );
            $itemStatement->execute([$itemId]);
            $item = $itemStatement->fetch();

            if (!$item) {
                throw new RuntimeException('Item not found.');
            }

            $userStatement = $pdo->prepare(
                'SELECT * FROM users WHERE id = ? FOR UPDATE'
            );
            $userStatement->execute([$user['id']]);
            $freshUser = $userStatement->fetch();

            $requirements = $this->decodeJson($item['requirements']);

            if (!$this->requirementsMet($freshUser, $requirements)) {
                throw new RuntimeException('The item requirements are not met.');
            }

            $totalPrice = (int) $item['price'] * $quantity;

            if ((int) $freshUser['cash'] < $totalPrice) {
                throw new RuntimeException('Not enough cash.');
            }

            $pdo->prepare(
                'UPDATE users SET cash = cash - ?, updated_at = NOW() WHERE id = ?'
            )->execute([$totalPrice, $freshUser['id']]);

            $inventoryStatement = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO user_items (
                        user_id,
                        item_definition_id,
                        quantity,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        quantity = quantity + VALUES(quantity),
                        updated_at = NOW()
                SQL
            );

            $inventoryStatement->execute([
                $freshUser['id'],
                $item['id'],
                $quantity,
            ]);

            (new EconomyLedgerService())->record(
                'equipment_purchase',
                $totalPrice,
                "Purchased {$quantity} × {$item['name']}",
                [
                    'source_type' => 'player',
                    'source_id' => $freshUser['id'],
                    'destination_type' => 'npc_shop',
                    'user_id' => $freshUser['id'],
                ]
            );

            AuditService::log(
                (int) $freshUser['id'],
                'shop.buy_item',
                [
                    'item_id' => $item['id'],
                    'quantity' => $quantity,
                    'total_price' => $totalPrice,
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Item purchased.',
                'item' => $item['name'],
                'quantity' => $quantity,
                'total_price' => $totalPrice,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function inventory(array $user): array
    {
        $pdo = Database::pdo();

        $itemStatement = $pdo->prepare(
            <<<'SQL'
                SELECT
                    inventory.id AS inventory_id,
                    inventory.quantity,
                    item.*,
                    (
                        SELECT COUNT(*)
                        FROM crew_equipment equipment
                        WHERE equipment.user_id = inventory.user_id
                          AND equipment.asset_type = 'item'
                          AND equipment.asset_id = item.id
                    ) AS equipped_quantity
                FROM user_items inventory
                JOIN item_definitions item
                    ON item.id = inventory.item_definition_id
                WHERE inventory.user_id = ?
                  AND inventory.quantity > 0
                ORDER BY item.category, item.name
            SQL
        );
        $itemStatement->execute([$user['id']]);
        $items = $itemStatement->fetchAll();

        foreach ($items as &$item) {
            $item['effects'] = $this->decodeJson($item['effects']);
            $item['item_effects'] = $this->decodeJson($item['item_effects'] ?? null);
            $item['allowed_slots'] = $this->decodeJson($item['allowed_slots'] ?? null);
            $item['item_tags'] = $this->decodeJson($item['item_tags'] ?? null);
            $item['available_quantity'] = max(
                0,
                (int) $item['quantity']
                - (int) $item['equipped_quantity']
                - $this->carriedQuantity((int) $user['id'], 'item', (int) $item['id'])
            );
        }

        $weaponStatement = $pdo->prepare(
            <<<'SQL'
                SELECT
                    inventory.quantity,
                    weapon.*,
                    (
                        SELECT COUNT(*)
                        FROM crew_equipment equipment
                        WHERE equipment.user_id = inventory.user_id
                          AND equipment.asset_type = 'weapon'
                          AND equipment.asset_id = weapon.id
                    ) AS equipped_quantity
                FROM user_weapons inventory
                JOIN weapons weapon
                    ON weapon.id = inventory.weapon_id
                WHERE inventory.user_id = ?
                  AND inventory.quantity > 0
                ORDER BY weapon.price, weapon.name
            SQL
        );
        $weaponStatement->execute([$user['id']]);
        $weapons = $weaponStatement->fetchAll();

        foreach ($weapons as &$weapon) {
            $weapon['effects'] = $this->decodeJson($weapon['effects']);
            $weapon['asset_type'] = 'weapon';
            $weapon['category'] = 'weapon';
            $weapon['size_class'] = in_array(($weapon['class'] ?? ''), ['shotgun', 'smg', 'assault_rifle'], true) ? 'large' : 'medium';
            $weapon['carry_units'] = in_array(($weapon['class'] ?? ''), ['shotgun', 'smg', 'assault_rifle'], true) ? 3 : 2;
            $weapon['legality'] = ((int) ($weapon['illegal'] ?? 1) === 1) ? 'illegal' : 'legal';
            $weapon['visible_illegal'] = (int) ($weapon['illegal'] ?? 1);
            $weapon['allowed_slots'] = $this->weaponSlots((string) ($weapon['class'] ?? ''), (string) ($weapon['equipment_slot'] ?? ''));
            $weapon['available_quantity'] = max(
                0,
                (int) $weapon['quantity'] - (int) $weapon['equipped_quantity']
            );
        }

        $drugStatement = $pdo->prepare(
            <<<'SQL'
                SELECT inventory.quantity, drug.*
                FROM user_drugs inventory
                JOIN drugs drug ON drug.id = inventory.drug_id
                WHERE inventory.user_id = ?
                  AND inventory.quantity > 0
                ORDER BY drug.name
            SQL
        );
        $drugStatement->execute([$user['id']]);

        return [
            'items' => $items,
            'weapons' => $weapons,
            'drugs' => $drugStatement->fetchAll(),
            'loadout_summary' => [
                'total_owned_items' => array_sum(array_map(static fn (array $item): int => (int) $item['quantity'], $items)),
                'equipped_items' => array_sum(array_map(static fn (array $item): int => (int) ($item['equipped_quantity'] ?? 0), $items)),
                'illegal_carried_items' => array_values(array_filter($items, static fn (array $item): bool => (int) ($item['illegal'] ?? 0) === 1)),
                'warnings' => ['Inventory is now owned-item management; use loadouts to equip or carry gear and map shops to buy.'],
            ],
            'equipment_slots' => (new EquipmentSlotService())->slots(),
        ];
    }


    private function carriedQuantity(int $userId, string $assetType, int $assetId): int
    {
        if (!$this->tableExists('character_carry_items')) {
            return 0;
        }

        $statement = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(quantity), 0) FROM character_carry_items WHERE user_id = ? AND asset_type = ? AND asset_id = ?'
        );
        $statement->execute([$userId, $assetType, $assetId]);

        return (int) $statement->fetchColumn();
    }

    private function weaponSlots(string $class, string $legacySlot): array
    {
        $class = strtolower($class);
        $legacySlot = strtolower($legacySlot);
        if (str_contains($class, 'pistol') || str_contains($class, 'revolver') || $legacySlot === 'sidearm') {
            return ['sidearm'];
        }
        if (str_contains($class, 'knife') || str_contains($class, 'baton') || str_contains($class, 'melee')) {
            return ['melee'];
        }
        return ['primary_weapon'];
    }

    private function tableExists(string $tableName): bool
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            SQL
        );
        $statement->execute([$tableName]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function requirementsMet(array $user, array $requirements): bool
    {
        $minimumReputation = (int) ($requirements['min_reputation'] ?? 0);

        return (int) ($user['reputation'] ?? 0) >= $minimumReputation;
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
