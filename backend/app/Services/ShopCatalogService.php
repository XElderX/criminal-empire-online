<?php

namespace App\Services;

use App\Config\ShopConfig;
use App\Core\Database;

final class ShopCatalogService
{
    public function shops(): array
    {
        return ShopConfig::shops();
    }

    public function catalog(): array
    {
        return ShopConfig::catalog();
    }

    public function itemConfig(string $itemKey): ?array
    {
        $catalog = ShopConfig::catalog();

        return $catalog[$itemKey] ?? null;
    }

    public function possibleSources(string $itemKey): array
    {
        $hints = ShopConfig::sourceHints();
        $config = $this->itemConfig($itemKey) ?? [];

        return array_map(
            fn (mixed $hint): array => $this->normalizeHint($itemKey, $hint, $config),
            $hints[$itemKey] ?? []
        );
    }

    public function shopsForItem(string $itemKey): array
    {
        $config = $this->itemConfig($itemKey);
        if (!$config || !($config['enabled'] ?? false)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($config['allowed_shop_types']), '?'));
        if ($placeholders === '') {
            return [];
        }

        $statement = Database::pdo()->prepare(
            <<<SQL
                SELECT shop.slug, shop.name, shop.shop_type, location.slug AS location_slug, location.name AS location_name,
                       region.slug AS region_slug, region.name AS region_name
                FROM shops shop
                JOIN world_locations location ON location.id = shop.world_location_id
                JOIN world_regions region ON region.id = shop.world_region_id
                WHERE shop.is_active = 1
                  AND shop.shop_type IN ({$placeholders})
                ORDER BY shop.name
            SQL
        );
        $statement->execute($config['allowed_shop_types']);

        return $statement->fetchAll();
    }

    public function sourceMapForItems(array $itemKeys): array
    {
        $sources = [];
        foreach ($itemKeys as $itemKey) {
            $sources[$itemKey] = [
                'hints' => $this->possibleSources((string) $itemKey),
                'shops' => $this->shopsForItem((string) $itemKey),
            ];
        }

        return $sources;
    }

    private function normalizeHint(string $itemKey, mixed $hint, array $config): array
    {
        if (is_array($hint)) {
            return array_merge([
                'item_key' => $itemKey,
                'item_name' => $config['name'] ?? $itemKey,
                'shop_slug' => '',
                'shop_name' => $hint['shop_name'] ?? $hint['label'] ?? 'Unknown shop',
                'location_slug' => $hint['location_slug'] ?? null,
                'location_label' => $hint['location_label'] ?? $hint['label'] ?? null,
                'availability_status' => $config['availability_status'] ?? 'unknown',
                'enabled' => (bool) ($config['enabled'] ?? false),
                'travel_hint' => 'Travel to the listed map shop to buy this item.',
            ], $hint);
        }

        $label = (string) $hint;
        $parts = array_map('trim', explode('/', $label));
        $shopName = count($parts) > 1 ? end($parts) : $label;

        return [
            'item_key' => $itemKey,
            'item_name' => $config['name'] ?? $itemKey,
            'shop_slug' => $this->slugify($shopName),
            'shop_name' => $shopName,
            'location_slug' => null,
            'location_label' => $label,
            'availability_status' => $config['availability_status'] ?? 'unknown',
            'enabled' => (bool) ($config['enabled'] ?? false),
            'travel_hint' => 'Travel to the listed map shop to buy this item.',
        ];
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?? '', '-'));

        return $slug !== '' ? $slug : 'shop-source';
    }
}
