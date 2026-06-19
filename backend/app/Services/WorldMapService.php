<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class WorldMapService
{
    private const WORLD_MAP = '/assets/maps/world/world_map.webp';
    private const WORLD_OVERLAY = '/assets/maps/world/world_map_overlay.svg';
    private const WORLD_FALLBACK = '/assets/maps/world/world_map_placeholder.svg';
    private const LOCATION_FALLBACK = '/assets/maps/placeholders/default_location.svg';
    private const REGION_FALLBACK = '/assets/maps/placeholders/default_region.svg';

    private MapRiskService $risk;

    public function __construct()
    {
        $this->risk = new MapRiskService();
    }

    public function overview(array $user): array
    {
        $regions = array_map(
            fn (array $region): array => $this->hydrateRegion($region),
            $this->activeRegions()
        );

        return [
            'world_name' => 'Grimwater County',
            'regions' => $regions,
            'currentLocation' => $this->currentLocation((int) $user['id']),
            'summary' => $this->summary((int) $user['id']),
            'mapAssets' => [
                'world_map' => self::WORLD_MAP,
                'world_overlay' => self::WORLD_OVERLAY,
                'fallback' => self::WORLD_FALLBACK,
            ],
            'legend' => $this->legend(),
        ];
    }

    public function regions(): array
    {
        return [
            'data' => array_map(
                fn (array $region): array => $this->hydrateRegion($region),
                $this->activeRegions()
            ),
        ];
    }

    public function region(array $user, string $slug): array
    {
        $region = $this->findRegion($slug);

        if (!$region || (int) $region['is_active'] !== 1) {
            throw new RuntimeException('World region not found.');
        }

        $locations = array_map(
            fn (array $location): array => $this->hydrateLocation($location),
            $this->locationsForRegion((int) $region['id'])
        );

        return [
            'world_name' => 'Grimwater County',
            'region' => $this->hydrateRegion($region),
            'locations' => $locations,
            'currentLocation' => $this->currentLocation((int) $user['id']),
            'territorySummary' => $this->territorySummary((int) $region['id']),
            'activitySummary' => $this->activitySummaryForLocations($locations),
            'mapAssets' => [
                'map' => $region['map_asset'] ?: self::REGION_FALLBACK,
                'overlay' => $region['overlay_asset'] ?: null,
                'fallback' => self::LOCATION_FALLBACK,
            ],
            'riskSummary' => $this->risk->summarize($region),
        ];
    }

    public function location(array $user, string $slug): array
    {
        $location = $this->findLocation($slug);

        if (!$location || (int) $location['is_active'] !== 1) {
            throw new RuntimeException('World location not found.');
        }

        $region = $this->findRegionById((int) $location['region_id']);
        $territory = $this->territoryForLocation($location);

        return [
            'location' => $this->hydrateLocation($location),
            'region' => $this->hydrateRegion($region),
            'territory' => $territory ? $this->formatTerritory($territory) : null,
            'linkedActions' => $this->activityLinks((int) $location['id']),
            'riskSummary' => $this->risk->summarize($location, $territory),
            'travelInfo' => $this->travelPreview($region, $location),
            'currentLocation' => $this->currentLocation((int) $user['id']),
        ];
    }

    public function currentLocation(int $userId): array
    {
        $this->ensureLocationState($userId);
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    state.*,
                    region.slug AS region_slug,
                    region.name AS region_name,
                    region.map_asset AS region_map_asset,
                    location.slug AS location_slug,
                    location.name AS location_name,
                    location.location_type,
                    location.heat_level,
                    location.police_pressure,
                    location.danger_level
                FROM user_location_state state
                LEFT JOIN world_regions region ON region.id = state.current_region_id
                LEFT JOIN world_locations location ON location.id = state.current_location_id
                WHERE state.user_id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$userId]);
        $row = $statement->fetch();

        return [
            'region_id' => isset($row['current_region_id']) ? (int) $row['current_region_id'] : null,
            'location_id' => isset($row['current_location_id']) ? (int) $row['current_location_id'] : null,
            'region_slug' => $row['region_slug'] ?? 'main-city',
            'region_name' => $row['region_name'] ?? 'Main City',
            'location_slug' => $row['location_slug'] ?? 'slums',
            'location_name' => $row['location_name'] ?? 'Slums',
            'location_type' => $row['location_type'] ?? 'district',
            'last_travel_at' => $row['last_travel_at'] ?? null,
            'travel_cooldown_until' => $row['travel_cooldown_until'] ?? null,
            'riskSummary' => $this->risk->summarize($row ?: []),
        ];
    }

    public function adminOverview(): array
    {
        $regions = Database::pdo()->query(
            <<<'SQL'
                SELECT *
                FROM world_regions
                ORDER BY sort_order, id
            SQL
        )->fetchAll();

        $locations = Database::pdo()->query(
            <<<'SQL'
                SELECT
                    location.*,
                    region.slug AS region_slug,
                    region.name AS region_name,
                    territory.name AS territory_name
                FROM world_locations location
                JOIN world_regions region ON region.id = location.region_id
                LEFT JOIN territories territory ON territory.id = location.linked_territory_id
                ORDER BY region.sort_order, location.sort_order, location.id
            SQL
        )->fetchAll();

        return [
            'world_name' => 'Grimwater County',
            'regions' => array_map(fn (array $region): array => $this->hydrateRegion($region, false), $regions),
            'locations' => array_map(fn (array $location): array => $this->hydrateLocation($location, false), $locations),
        ];
    }

    public function ensureLocationState(int $userId): void
    {
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM user_location_state WHERE user_id = ? LIMIT 1');
        $exists->execute([$userId]);

        if ($exists->fetchColumn()) {
            return;
        }

        $regionId = (int) $pdo->query("SELECT id FROM world_regions WHERE slug = 'main-city' LIMIT 1")->fetchColumn();
        $locationId = (int) $pdo->query("SELECT id FROM world_locations WHERE slug = 'slums' LIMIT 1")->fetchColumn();

        $pdo->prepare(
            <<<'SQL'
                INSERT INTO user_location_state (
                    user_id,
                    current_region_id,
                    current_location_id,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NOW(), NOW())
            SQL
        )->execute([$userId, $regionId ?: null, $locationId ?: null]);
    }

    public function activeRegions(): array
    {
        return Database::pdo()->query(
            <<<'SQL'
                SELECT *
                FROM world_regions
                WHERE is_active = 1
                ORDER BY sort_order, id
            SQL
        )->fetchAll();
    }

    public function findRegion(string $slug): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM world_regions WHERE slug = ? LIMIT 1');
        $statement->execute([$slug]);
        $region = $statement->fetch();

        return $region ?: null;
    }

    public function findRegionById(int $id): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM world_regions WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $region = $statement->fetch();

        if (!$region) {
            throw new RuntimeException('World region not found.');
        }

        return $region;
    }

    public function findLocation(string $slug): ?array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    location.*,
                    region.slug AS region_slug,
                    region.name AS region_name,
                    territory.name AS territory_name,
                    territory.population,
                    territory.wealth,
                    territory.crime_rate,
                    territory.government_presence,
                    territory.district_heat,
                    gang.name AS owner_gang
                FROM world_locations location
                JOIN world_regions region ON region.id = location.region_id
                LEFT JOIN territories territory ON territory.id = location.linked_territory_id
                LEFT JOIN gangs gang ON gang.id = territory.owner_gang_id
                WHERE location.slug = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$slug]);
        $location = $statement->fetch();

        return $location ?: null;
    }

    public function locationsForRegion(int $regionId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    location.*,
                    region.slug AS region_slug,
                    region.name AS region_name,
                    territory.name AS territory_name,
                    territory.population,
                    territory.wealth,
                    territory.crime_rate,
                    territory.government_presence,
                    territory.district_heat,
                    gang.name AS owner_gang
                FROM world_locations location
                JOIN world_regions region ON region.id = location.region_id
                LEFT JOIN territories territory ON territory.id = location.linked_territory_id
                LEFT JOIN gangs gang ON gang.id = territory.owner_gang_id
                WHERE location.region_id = ?
                  AND location.is_active = 1
                ORDER BY location.sort_order, location.id
            SQL
        );
        $statement->execute([$regionId]);

        return $statement->fetchAll();
    }

    public function hydrateRegion(array $region, bool $includeOnlyActive = true): array
    {
        return [
            'id' => (int) $region['id'],
            'slug' => $region['slug'],
            'name' => $region['name'],
            'description' => $region['description'],
            'region_type' => $region['region_type'],
            'map_asset' => $region['map_asset'] ?: self::REGION_FALLBACK,
            'overlay_asset' => $region['overlay_asset'] ?: null,
            'travel_cost_cash' => (int) $region['travel_cost_cash'],
            'travel_cost_energy' => (int) $region['travel_cost_energy'],
            'base_heat' => (int) $region['base_heat'],
            'police_pressure' => (int) $region['police_pressure'],
            'danger_level' => (int) $region['danger_level'],
            'recommended_level' => (int) $region['recommended_level'],
            'is_active' => (bool) $region['is_active'],
            'sort_order' => (int) $region['sort_order'],
            'riskSummary' => $this->risk->summarize($region),
        ];
    }

    public function hydrateLocation(array $location, bool $includeActions = true): array
    {
        $territory = $this->territoryForLocation($location);
        $actions = $includeActions ? $this->activityLinks((int) $location['id']) : [];
        $available = $this->decodeJson($location['available_actions_json'] ?? null);

        return [
            'id' => (int) $location['id'],
            'region_id' => (int) $location['region_id'],
            'region_slug' => $location['region_slug'] ?? null,
            'region_name' => $location['region_name'] ?? null,
            'slug' => $location['slug'],
            'name' => $location['name'],
            'description' => $location['description'],
            'location_type' => $location['location_type'],
            'x_percent' => (float) $location['x_percent'],
            'y_percent' => (float) $location['y_percent'],
            'heat_level' => (int) $location['heat_level'],
            'police_pressure' => (int) $location['police_pressure'],
            'danger_level' => (int) $location['danger_level'],
            'min_level' => (int) $location['min_level'],
            'linked_feature_key' => $location['linked_feature_key'] ?? null,
            'available_actions' => $available,
            'actions' => $actions,
            'territory' => $territory ? $this->formatTerritory($territory) : null,
            'is_active' => (bool) $location['is_active'],
            'riskSummary' => $this->risk->summarize($location, $territory),
        ];
    }

    public function activityLinks(int $locationId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM map_activity_links
                WHERE world_location_id = ?
                  AND is_active = 1
                ORDER BY sort_order, id
            SQL
        );
        $statement->execute([$locationId]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'feature_type' => $row['feature_type'],
            'feature_key' => $row['feature_key'],
            'label' => $row['label'],
            'route_hint' => $row['route_hint'],
            'min_level' => (int) $row['min_level'],
        ], $statement->fetchAll());
    }

    public function territoryForLocation(array $location): ?array
    {
        if (empty($location['linked_territory_id'])) {
            return null;
        }

        if (isset($location['territory_name'])) {
            return $location;
        }

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT territory.*, gang.name AS owner_gang
                FROM territories territory
                LEFT JOIN gangs gang ON gang.id = territory.owner_gang_id
                WHERE territory.id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$location['linked_territory_id']]);
        $territory = $statement->fetch();

        return $territory ?: null;
    }

    public function formatTerritory(array $territory): array
    {
        $owner = $territory['owner_gang'] ?? null;
        $control = $owner ? 'Controlled by ' . $owner : 'Neutral';

        if ((int) ($territory['government_presence'] ?? 0) >= 70) {
            $control = 'Police-heavy';
        }

        return [
            'id' => isset($territory['linked_territory_id']) ? (int) $territory['linked_territory_id'] : (int) ($territory['id'] ?? 0),
            'name' => $territory['territory_name'] ?? $territory['name'] ?? 'Unknown territory',
            'owner_gang' => $owner,
            'control_label' => $control,
            'population' => (int) ($territory['population'] ?? 0),
            'wealth' => (int) ($territory['wealth'] ?? 0),
            'crime_rate' => (int) ($territory['crime_rate'] ?? 0),
            'government_presence' => (int) ($territory['government_presence'] ?? 0),
            'district_heat' => (int) ($territory['district_heat'] ?? 0),
        ];
    }

    public function travelPreview(array $region, array $location): array
    {
        $energy = max(0, (int) $region['travel_cost_energy'] + (int) floor(((int) $location['danger_level']) / 25));
        $cash = max(0, (int) $region['travel_cost_cash']);

        return [
            'cash_cost' => $cash,
            'energy_cost' => $energy,
            'warnings' => $this->travelWarnings($location),
            'locked_reason' => null,
        ];
    }

    private function travelWarnings(array $location): array
    {
        $warnings = [];

        if ((int) $location['police_pressure'] >= 70) {
            $warnings[] = 'Police presence is high here.';
        }

        if ((int) $location['danger_level'] >= 55) {
            $warnings[] = 'This is a dangerous hotspot.';
        }

        if ((int) $location['heat_level'] >= 45) {
            $warnings[] = 'Local heat may affect crimes and contacts.';
        }

        return $warnings;
    }

    private function territorySummary(int $regionId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*) AS linked_count
                FROM world_locations
                WHERE region_id = ?
                  AND linked_territory_id IS NOT NULL
            SQL
        );
        $statement->execute([$regionId]);

        return ['linked_territories' => (int) $statement->fetchColumn()];
    }

    private function activitySummaryForLocations(array $locations): array
    {
        $counts = [];

        foreach ($locations as $location) {
            foreach ($location['actions'] ?? [] as $action) {
                $key = $action['feature_type'];
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function summary(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT cash, energy, max_energy, heat, boss_personal_heat, gang_heat
                FROM users
                WHERE id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$userId]);
        $user = $statement->fetch() ?: [];

        return [
            'cash' => (int) ($user['cash'] ?? 0),
            'energy' => (int) ($user['energy'] ?? 0),
            'max_energy' => (int) ($user['max_energy'] ?? 0),
            'display_heat' => max((int) ($user['heat'] ?? 0), (int) ($user['boss_personal_heat'] ?? 0), (int) ($user['gang_heat'] ?? 0)),
            'world_name' => 'Grimwater County',
        ];
    }

    private function legend(): array
    {
        return [
            ['type' => 'district', 'label' => 'District'],
            ['type' => 'safehouse', 'label' => 'Safehouse'],
            ['type' => 'garage', 'label' => 'Garage'],
            ['type' => 'black_market', 'label' => 'Black Market'],
            ['type' => 'police', 'label' => 'Police Station'],
            ['type' => 'business', 'label' => 'Business'],
            ['type' => 'recruitment', 'label' => 'Recruitment'],
            ['type' => 'warehouse', 'label' => 'Warehouse'],
            ['type' => 'point_of_interest', 'label' => 'Point of Interest'],
        ];
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
