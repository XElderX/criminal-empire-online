<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class ShopTransactionService
{
    public function buy(array $user, string $shopSlug, string $itemKey, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 20) {
            throw new RuntimeException('Quantity must be between 1 and 20.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $shopService = new ShopService();
            $shop = $shopService->findShop($shopSlug);
            (new ShopRestockService())->restockShop((int) $shop['id']);
            $item = $shopService->itemRow($shop, $itemKey);
            $freshUser = $this->lockUser((int) $user['id']);
            $currentLocation = (new WorldMapService())->currentLocation((int) $freshUser['id']);
            $playerIsHere = $shopService->playerIsAtShop($currentLocation, $shop);
            $availability = (new ShopAvailabilityService())->availability($freshUser, $shop, $item, $playerIsHere);

            if ($availability['locked_reasons'] !== []) {
                throw new RuntimeException(implode(' ', $availability['locked_reasons']));
            }
            if (!$availability['can_buy']) {
                throw new RuntimeException('This item cannot be bought right now.');
            }
            if ((int) $item['is_enabled'] !== 1 || (int) $item['can_buy'] !== 1) {
                throw new RuntimeException($item['disabled_reason'] ?: 'Item sale is disabled by shop config.');
            }
            if ($item['asset_type'] !== 'item') {
                throw new RuntimeException('This item is not available through regular shops yet.');
            }

            $definition = $this->lockItemDefinition((string) $item['item_key']);
            $total = (int) $item['buy_price'] * $quantity;

            if ((int) $freshUser['cash'] < $total) {
                throw new RuntimeException('Not enough cash.');
            }
            if ($item['stock_quantity'] !== null && (int) $item['stock_quantity'] < $quantity) {
                throw new RuntimeException('Shop does not have enough stock.');
            }

            $pdo->prepare('UPDATE users SET cash = cash - ?, updated_at = NOW() WHERE id = ?')
                ->execute([$total, $freshUser['id']]);

            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO user_items (user_id, item_definition_id, quantity, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = NOW()
                SQL
            )->execute([$freshUser['id'], $definition['id'], $quantity]);

            if ($item['stock_quantity'] !== null) {
                $pdo->prepare('UPDATE shop_items SET stock_quantity = stock_quantity - ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$quantity, $item['id']]);
            }

            $transactionId = $this->recordTransaction(
                (int) $freshUser['id'],
                (int) $shop['id'],
                (string) $item['item_key'],
                'item',
                'buy',
                $quantity,
                (int) $item['buy_price'],
                $total
            );

            (new EconomyLedgerService())->record(
                'shop_purchase',
                $total,
                "Purchased {$quantity} × {$item['item_name']} at {$shop['name']}",
                [
                    'source_type' => 'player',
                    'source_id' => $freshUser['id'],
                    'destination_type' => 'map_shop',
                    'destination_id' => $shop['id'],
                    'user_id' => $freshUser['id'],
                ]
            );

            AuditService::log((int) $freshUser['id'], 'shop.buy', [
                'shop_slug' => $shopSlug,
                'item_key' => $itemKey,
                'quantity' => $quantity,
                'total_price' => $total,
            ]);

            $pdo->commit();

            return [
                'message' => "Purchased {$quantity} × {$item['item_name']}.",
                'transaction_id' => $transactionId,
                'item_key' => $item['item_key'],
                'quantity' => $quantity,
                'total_price' => $total,
                'cash_remaining' => (int) $freshUser['cash'] - $total,
                'shop' => ['slug' => $shop['slug'], 'name' => $shop['name']],
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function sell(array $user, string $shopSlug, string $itemKey, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 100) {
            throw new RuntimeException('Quantity must be between 1 and 100.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $shopService = new ShopService();
            $shop = $shopService->findShop($shopSlug);
            $freshUser = $this->lockUser((int) $user['id']);
            $currentLocation = (new WorldMapService())->currentLocation((int) $freshUser['id']);
            $playerIsHere = $shopService->playerIsAtShop($currentLocation, $shop);

            if ((int) $shop['requires_local_presence'] === 1 && !$playerIsHere) {
                throw new RuntimeException('Travel to this shop location before selling.');
            }

            $definition = $this->lockItemDefinition($itemKey);
            $buyCategories = $this->decodeJson($shop['buys_categories_json'] ?? '[]');
            if (!in_array($definition['category'], $buyCategories, true)) {
                throw new RuntimeException('This shop does not buy that item category.');
            }
            if ((int) $shop['is_legal'] === 1 && (int) $definition['illegal'] === 1) {
                throw new RuntimeException('This legal shop refuses suspicious goods. Find a fence.');
            }

            $inventory = $this->lockUserItem((int) $freshUser['id'], (int) $definition['id']);
            $equipped = $this->equippedQuantity((int) $freshUser['id'], (int) $definition['id']);
            $available = (int) $inventory['quantity'] - $equipped;
            if ($available < $quantity) {
                throw new RuntimeException('Not enough unequipped quantity to sell.');
            }

            $price = (new ItemPricingService())->sellPrice($definition, 0.45);
            $total = $price * $quantity;

            $pdo->prepare('UPDATE user_items SET quantity = quantity - ?, updated_at = NOW() WHERE id = ?')
                ->execute([$quantity, $inventory['id']]);
            $pdo->prepare('DELETE FROM user_items WHERE id = ? AND quantity <= 0')
                ->execute([$inventory['id']]);
            $pdo->prepare('UPDATE users SET cash = cash + ?, updated_at = NOW() WHERE id = ?')
                ->execute([$total, $freshUser['id']]);

            $transactionId = $this->recordTransaction(
                (int) $freshUser['id'],
                (int) $shop['id'],
                $itemKey,
                'item',
                'sell',
                $quantity,
                $price,
                $total,
                (int) $inventory['id']
            );

            (new EconomyLedgerService())->record(
                'shop_sale',
                $total,
                "Sold {$quantity} × {$definition['name']} at {$shop['name']}",
                [
                    'source_type' => 'map_shop',
                    'source_id' => $shop['id'],
                    'destination_type' => 'player',
                    'destination_id' => $freshUser['id'],
                    'user_id' => $freshUser['id'],
                ]
            );

            AuditService::log((int) $freshUser['id'], 'shop.sell', [
                'shop_slug' => $shopSlug,
                'item_key' => $itemKey,
                'quantity' => $quantity,
                'total_price' => $total,
            ]);

            $pdo->commit();

            return [
                'message' => "Sold {$quantity} × {$definition['name']}.",
                'transaction_id' => $transactionId,
                'item_key' => $itemKey,
                'quantity' => $quantity,
                'total_price' => $total,
                'cash_after_sale' => (int) $freshUser['cash'] + $total,
                'shop' => ['slug' => $shop['slug'], 'name' => $shop['name']],
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function lockUser(int $userId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user) {
            throw new RuntimeException('Player not found.');
        }
        return $user;
    }

    private function lockItemDefinition(string $itemKey): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM item_definitions WHERE code = ? AND active = 1 LIMIT 1 FOR UPDATE');
        $statement->execute([$itemKey]);
        $item = $statement->fetch();
        if (!$item) {
            throw new RuntimeException('Item definition not found.');
        }
        return $item;
    }

    private function lockUserItem(int $userId, int $itemId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM user_items WHERE user_id = ? AND item_definition_id = ? FOR UPDATE');
        $statement->execute([$userId, $itemId]);
        $inventory = $statement->fetch();
        if (!$inventory || (int) $inventory['quantity'] < 1) {
            throw new RuntimeException('You do not own that item.');
        }
        return $inventory;
    }

    private function equippedQuantity(int $userId, int $itemId): int
    {
        $statement = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM crew_equipment WHERE user_id = ? AND asset_type = 'item' AND asset_id = ?"
        );
        $statement->execute([$userId, $itemId]);
        return (int) $statement->fetchColumn();
    }

    private function recordTransaction(
        int $userId,
        int $shopId,
        string $itemKey,
        string $assetType,
        string $actionType,
        int $quantity,
        int $unitPrice,
        int $totalPrice,
        ?int $sourceInventoryId = null
    ): int {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO shop_transactions (
                    user_id, shop_id, item_key, asset_type, action_type, quantity,
                    unit_price, total_price, source_inventory_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        );
        $statement->execute([
            $userId,
            $shopId,
            $itemKey,
            $assetType,
            $actionType,
            $quantity,
            $unitPrice,
            $totalPrice,
            $sourceInventoryId,
        ]);

        return (int) Database::pdo()->lastInsertId();
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
