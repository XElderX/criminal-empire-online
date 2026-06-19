<?php

namespace App\Services;

use App\Core\Database;

final class ShopRestockService
{
    public function restockShop(int $shopId): void
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE shop_items
                SET stock_quantity = max_stock,
                    last_restocked_at = NOW(),
                    updated_at = NOW()
                WHERE shop_id = ?
                  AND max_stock IS NOT NULL
                  AND restock_interval_minutes IS NOT NULL
                  AND (last_restocked_at IS NULL OR last_restocked_at <= DATE_SUB(NOW(), INTERVAL restock_interval_minutes MINUTE))
                  AND stock_quantity < max_stock
            SQL
        );
        $statement->execute([$shopId]);
    }
}
