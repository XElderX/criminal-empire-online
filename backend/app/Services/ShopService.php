<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class ShopService
{
    private ShopAvailabilityService $availability;

    public function __construct()
    {
        $this->availability = new ShopAvailabilityService();
    }

    public function list(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT shop.*, region.slug AS region_slug, region.name AS region_name,
                       location.slug AS location_slug, location.name AS location_name,
                       (
                           SELECT COUNT(*)
                           FROM shop_items item
                           WHERE item.shop_id = shop.id
                       ) AS catalog_count
                FROM shops shop
                JOIN world_regions region ON region.id = shop.world_region_id
                JOIN world_locations location ON location.id = shop.world_location_id
                WHERE shop.is_active = 1
                  AND (shop.is_known = 1 OR shop.min_reputation <= ?)
                ORDER BY shop.is_black_market, region.sort_order, location.sort_order, shop.name
            SQL
        );
        $statement->execute([(int) ($user['reputation'] ?? 0)]);
        $shops = $statement->fetchAll();
        $current = (new WorldMapService())->currentLocation((int) $user['id']);

        return [
            'data' => array_map(fn (array $shop): array => $this->formatShop($shop, $current), $shops),
            'currentLocation' => $current,
            'configVersion' => \App\Config\ShopConfig::VERSION,
        ];
    }

    public function detail(array $user, string $slug): array
    {
        $shop = $this->findShop($slug);
        (new ShopRestockService())->restockShop((int) $shop['id']);
        $current = (new WorldMapService())->currentLocation((int) $user['id']);
        $playerIsHere = $this->playerIsAtShop($current, $shop);

        if ((int) $shop['can_view_remotely'] !== 1 && !$playerIsHere) {
            throw new RuntimeException('This contact is hidden until you visit the hotspot.');
        }

        return [
            'shop' => $this->formatShop($shop, $current),
            'items' => $this->itemsForShop($user, $shop, $playerIsHere),
            'sellableInventory' => $this->sellableInventory($user, $shop, $playerIsHere),
            'currentLocation' => $current,
            'localPresenceRequired' => (bool) $shop['requires_local_presence'],
            'localPresenceSatisfied' => $playerIsHere || (int) $shop['requires_local_presence'] !== 1,
            'message' => $playerIsHere ? 'You are at this shop.' : 'You can browse remotely, but transactions require local presence.',
        ];
    }

    public function shopsForLocation(array $user, string $locationSlug): array
    {
        $map = new WorldMapService();
        $location = $map->findLocation($locationSlug);
        if (!$location) {
            throw new RuntimeException('World location not found.');
        }

        return [
            'location' => $map->hydrateLocation($location),
            'shops' => (new MapShopService())->forLocation((int) $user['id'], (int) $location['id']),
            'currentLocation' => $map->currentLocation((int) $user['id']),
        ];
    }

    public function history(array $user, string $slug): array
    {
        $shop = $this->findShop($slug);
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM shop_transactions
                WHERE user_id = ?
                  AND shop_id = ?
                ORDER BY id DESC
                LIMIT 30
            SQL
        );
        $statement->execute([$user['id'], $shop['id']]);

        return ['data' => $statement->fetchAll()];
    }

    public function findShop(string $slug): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT shop.*, region.slug AS region_slug, region.name AS region_name,
                       location.slug AS location_slug, location.name AS location_name,
                       location.police_pressure, location.danger_level
                FROM shops shop
                JOIN world_regions region ON region.id = shop.world_region_id
                JOIN world_locations location ON location.id = shop.world_location_id
                WHERE shop.slug = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$slug]);
        $shop = $statement->fetch();

        if (!$shop || (int) $shop['is_active'] !== 1) {
            throw new RuntimeException('Shop not found.');
        }

        return $shop;
    }

    public function itemRow(array $shop, string $itemKey): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM shop_items WHERE shop_id = ? AND item_key = ? LIMIT 1 FOR UPDATE'
        );
        $statement->execute([$shop['id'], $itemKey]);
        $item = $statement->fetch();

        if (!$item) {
            throw new RuntimeException('This shop does not stock that item.');
        }

        return $this->applyConfigToItem($shop, $item);
    }

    public function playerIsAtShop(array $currentLocation, array $shop): bool
    {
        return (int) ($currentLocation['location_id'] ?? 0) === (int) $shop['world_location_id'];
    }

    public function itemsForShop(array $user, array $shop, bool $playerIsHere): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT shop_item.*, item.id AS item_definition_id, item.code AS item_code,
                       item.description AS definition_description, item.effects AS definition_effects,
                       item.equipment_slot AS definition_equipment_slot,
                       item.illegal AS definition_illegal,
                       COALESCE(inventory.quantity, 0) AS owned_quantity
                FROM shop_items shop_item
                LEFT JOIN item_definitions item ON item.code = shop_item.item_key AND shop_item.asset_type = 'item'
                LEFT JOIN user_items inventory ON inventory.item_definition_id = item.id AND inventory.user_id = ?
                WHERE shop_item.shop_id = ?
                ORDER BY shop_item.is_enabled DESC, shop_item.item_category, shop_item.buy_price, shop_item.item_name
            SQL
        );
        $statement->execute([$user['id'], $shop['id']]);
        $rows = $statement->fetchAll();

        return array_map(function (array $row) use ($user, $shop, $playerIsHere): array {
            $row = $this->applyConfigToItem($shop, $row);
            $availability = $this->availability->availability($user, $shop, $row, $playerIsHere);
            $effects = $this->decodeJson($row['definition_effects'] ?? null);

            return [
                'id' => (int) $row['id'],
                'item_key' => $row['item_key'],
                'asset_type' => $row['asset_type'],
                'name' => $row['item_name'],
                'item_name' => $row['item_name'],
                'category' => $row['item_category'],
                'description' => $row['description'] ?: ($row['definition_description'] ?? ''),
                'equipment_slot' => $row['definition_equipment_slot'] ?? null,
                'effects' => $effects,
                'buy_price' => (int) $row['buy_price'],
                'sell_price_multiplier' => (float) $row['sell_price_multiplier'],
                'stock_quantity' => isset($row['stock_quantity']) ? (int) $row['stock_quantity'] : null,
                'max_stock' => isset($row['max_stock']) ? (int) $row['max_stock'] : null,
                'min_level' => (int) $row['min_level'],
                'min_reputation' => (int) $row['min_reputation'],
                'availability_status' => $row['availability_status'],
                'is_enabled' => (bool) $row['is_enabled'],
                'disabled_reason' => $row['disabled_reason'],
                'can_buy' => $availability['can_buy'],
                'can_sell' => $availability['can_sell'],
                'locked_reasons' => $availability['locked_reasons'],
                'warnings' => $availability['warnings'],
                'owned_quantity' => (int) $row['owned_quantity'],
                'is_illegal' => (bool) ($row['definition_illegal'] ?? ($row['availability_status'] !== 'legal')),
            ];
        }, $rows);
    }

    public function sellableInventory(array $user, array $shop, bool $playerIsHere): array
    {
        if ((int) $shop['requires_local_presence'] === 1 && !$playerIsHere) {
            return [];
        }

        $categories = $this->decodeJson($shop['buys_categories_json'] ?? '[]');
        if ($categories === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $sql = <<<SQL
            SELECT inventory.id AS inventory_id, inventory.quantity, item.*,
                   (
                       SELECT COUNT(*)
                       FROM crew_equipment equipment
                       WHERE equipment.user_id = inventory.user_id
                         AND equipment.asset_type = 'item'
                         AND equipment.asset_id = item.id
                   ) AS equipped_quantity
            FROM user_items inventory
            JOIN item_definitions item ON item.id = inventory.item_definition_id
            WHERE inventory.user_id = ?
              AND inventory.quantity > 0
              AND item.category IN ({$placeholders})
            ORDER BY item.category, item.name
        SQL;
        $statement = Database::pdo()->prepare($sql);
        $statement->execute(array_merge([$user['id']], $categories));
        $rows = $statement->fetchAll();
        $pricing = new ItemPricingService();

        return array_map(static function (array $row) use ($pricing): array {
            $available = max(0, (int) $row['quantity'] - (int) $row['equipped_quantity']);
            return [
                'inventory_id' => (int) $row['inventory_id'],
                'item_key' => $row['code'],
                'name' => $row['name'],
                'category' => $row['category'],
                'quantity' => (int) $row['quantity'],
                'available_quantity' => $available,
                'sell_price' => $pricing->sellPrice($row, 0.45),
                'can_sell' => $available > 0,
                'effects' => json_decode((string) ($row['effects'] ?? ''), true) ?: [],
            ];
        }, $rows);
    }


    private function applyConfigToItem(array $shop, array $item): array
    {
        $config = (new ShopCatalogService())->itemConfig((string) $item['item_key']);

        if (!$config) {
            $item['is_enabled'] = 0;
            $item['disabled_reason'] = 'missing_shop_config';
            return $item;
        }

        $allowedTypes = $config['allowed_shop_types'] ?? [];
        if ($allowedTypes !== [] && !in_array($shop['shop_type'], $allowedTypes, true)) {
            $item['is_enabled'] = 0;
            $item['disabled_reason'] = 'not_available_in_this_shop_type';
        }

        if (!($config['enabled'] ?? false)) {
            $item['is_enabled'] = 0;
            $item['can_buy'] = 0;
            $item['disabled_reason'] = $config['disabled_reason'] ?? $config['availability_status'] ?? 'disabled_by_shop_config';
        }

        $item['availability_status'] = $config['availability_status'] ?? $item['availability_status'];
        $item['min_level'] = max((int) $item['min_level'], (int) ($config['min_level'] ?? 1));
        $item['min_reputation'] = max((int) $item['min_reputation'], (int) ($config['min_reputation'] ?? 0));
        $item['heat_risk'] = max((int) $item['heat_risk'], (int) ($config['heat_risk'] ?? 0));

        return $item;
    }

    private function formatShop(array $shop, array $currentLocation): array
    {
        $isHere = $this->playerIsAtShop($currentLocation, $shop);
        return [
            'id' => (int) $shop['id'],
            'slug' => $shop['slug'],
            'name' => $shop['name'],
            'description' => $shop['description'],
            'shop_type' => $shop['shop_type'],
            'region_slug' => $shop['region_slug'],
            'region_name' => $shop['region_name'],
            'location_slug' => $shop['location_slug'],
            'location_name' => $shop['location_name'],
            'location_label' => $shop['region_name'] . ' / ' . $shop['location_name'],
            'requires_local_presence' => (bool) $shop['requires_local_presence'],
            'local_presence_satisfied' => $isHere || (int) $shop['requires_local_presence'] !== 1,
            'can_view_remotely' => (bool) $shop['can_view_remotely'],
            'is_black_market' => (bool) $shop['is_black_market'],
            'is_legal' => (bool) $shop['is_legal'],
            'is_known' => (bool) $shop['is_known'],
            'heat_risk' => (int) $shop['heat_risk'],
            'min_level' => (int) $shop['min_level'],
            'min_reputation' => (int) $shop['min_reputation'],
            'catalog_count' => isset($shop['catalog_count']) ? (int) $shop['catalog_count'] : null,
            'travel_hint' => $isHere ? null : 'Travel here to buy or sell.',
        ];
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
