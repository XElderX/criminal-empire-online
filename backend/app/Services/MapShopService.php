<?php

namespace App\Services;

use App\Core\Database;

final class MapShopService
{
    public function forLocation(int $userId, int $locationId): array
    {
        $playerLocation = (new WorldMapService())->currentLocation($userId);
        $playerIsHere = (int) ($playerLocation['location_id'] ?? 0) === $locationId;

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT shop.*, region.slug AS region_slug, region.name AS region_name,
                       location.slug AS location_slug, location.name AS location_name
                FROM shops shop
                JOIN world_regions region ON region.id = shop.world_region_id
                JOIN world_locations location ON location.id = shop.world_location_id
                WHERE shop.world_location_id = ?
                  AND shop.is_active = 1
                  AND (shop.is_known = 1 OR ? = 1)
                ORDER BY shop.is_black_market, shop.name
            SQL
        );
        $statement->execute([$locationId, $playerIsHere ? 1 : 0]);
        $shops = $statement->fetchAll();

        return array_map(fn (array $shop): array => $this->formatShopPreview($shop, $playerIsHere), $shops);
    }

    public function countsForLocation(int $userId, int $locationId): array
    {
        $shops = $this->forLocation($userId, $locationId);
        $available = 0;
        $locked = 0;
        foreach ($shops as $shop) {
            $shop['localPresenceSatisfied'] ? $available++ : $locked++;
        }

        return ['available' => $available, 'locked' => $locked, 'shops' => $shops];
    }

    private function formatShopPreview(array $shop, bool $playerIsHere): array
    {
        return [
            'id' => (int) $shop['id'],
            'slug' => $shop['slug'],
            'title' => $shop['name'],
            'name' => $shop['name'],
            'shop_type' => $shop['shop_type'],
            'description' => $shop['description'],
            'location' => $shop['region_name'] . ' / ' . $shop['location_name'],
            'route_hint' => 'shops',
            'requiresLocalPresence' => (bool) $shop['requires_local_presence'],
            'localPresenceSatisfied' => $playerIsHere || (int) $shop['requires_local_presence'] !== 1,
            'localPresenceStatus' => $playerIsHere ? 'available_here' : ((int) $shop['requires_local_presence'] === 1 ? 'travel_required' : 'remote_available'),
            'travelHint' => $playerIsHere ? null : 'Travel here to buy or sell.',
            'is_black_market' => (bool) $shop['is_black_market'],
            'is_legal' => (bool) $shop['is_legal'],
            'heat_risk' => (int) $shop['heat_risk'],
        ];
    }
}
