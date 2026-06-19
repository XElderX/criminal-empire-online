<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail($root . '/backend/database/migrations/011_v06_world_map_and_territories.sql');
$seed = readFileOrFail($root . '/backend/database/seeders/011_v06_world_map_and_territories_seed.sql');
$service = readFileOrFail($root . '/backend/app/Services/WorldMapService.php');
$travel = readFileOrFail($root . '/backend/app/Services/TravelService.php');
$risk = readFileOrFail($root . '/backend/app/Services/MapRiskService.php');
$controller = readFileOrFail($root . '/backend/app/Controllers/WorldMapController.php');
$routes = readFileOrFail($root . '/backend/routes/api.php');
$worldPage = readFileOrFail($root . '/frontend/src/pages/WorldMapPage.tsx');
$locationPage = readFileOrFail($root . '/frontend/src/pages/LocationMapPage.tsx');
$manifest = readFileOrFail($root . '/frontend/src/data/mapAssetManifest.ts');
$types = readFileOrFail($root . '/frontend/src/types/worldMap.ts');
$nav = readFileOrFail($root . '/frontend/src/components/Navigation.tsx');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');

$runner->test('v0.6 migration adds world map tables', function () use ($runner, $migration): void {
    foreach (['world_regions', 'world_locations', 'user_location_state', 'territory_map_links', 'map_activity_links'] as $table) {
        $runner->assertContains($table, $migration);
    }
});

$runner->test('v0.6 seed includes all required major regions', function () use ($runner, $seed): void {
    foreach (['Main City', 'Suburbs', 'Industrial Zone', 'Docks', 'Rural County', 'Forest / Hills', 'Shore / Beach / Sea', 'Old Town', 'Highway / Outskirts'] as $region) {
        $runner->assertContains($region, $seed);
    }
});

$runner->test('v0.6 seed includes required hotspots', function () use ($runner, $seed): void {
    foreach (['Downtown', 'Police District', 'Container Yard', 'Factory Blocks', 'Roadside Motel', 'Hidden Camp', 'Sea Caves', 'Private Estates', 'Border Road'] as $hotspot) {
        $runner->assertContains($hotspot, $seed);
    }
});

$runner->test('World map service exposes overview, region, location and current location', function () use ($runner, $service): void {
    foreach (['function overview', 'function region', 'function location', 'function currentLocation', 'ensureLocationState', 'Grimwater County'] as $needle) {
        $runner->assertContains($needle, $service);
    }
});

$runner->test('Travel service validates cash, energy and user ownership state', function () use ($runner, $travel): void {
    foreach (['Not enough cash', 'Not enough energy', 'FOR UPDATE', 'user_location_state', 'world_map.travel'] as $needle) {
        $runner->assertContains($needle, $travel);
    }
});

$runner->test('Risk service includes heat, police and danger summary', function () use ($runner, $risk): void {
    foreach (['heat', 'police_pressure', 'danger_level', 'Police Heavy', 'Hot Zone'] as $needle) {
        $runner->assertContains($needle, $risk);
    }
});

$runner->test('World map API routes exist', function () use ($runner, $routes): void {
    foreach (['/api/world-map', '/api/world-map/regions', '/api/world-map/regions/{slug}', '/api/world-map/locations/{slug}', '/api/world-map/current-location', '/api/world-map/travel', '/api/admin/world-map'] as $route) {
        $runner->assertContains($route, $routes);
    }
});

$runner->test('World map controller has player and admin methods', function () use ($runner, $controller): void {
    foreach (['function index', 'function region', 'function location', 'function travel', 'function adminOverview', 'AdminMiddleware'] as $needle) {
        $runner->assertContains($needle, $controller);
    }
});

$runner->test('Frontend map pages and components are wired', function () use ($runner, $worldPage, $locationPage, $nav): void {
    foreach (['WorldMap', 'MapRegionCard', 'TravelPanel', 'Grimwater County'] as $needle) {
        $runner->assertContains($needle, $worldPage);
    }
    foreach (['LocationMap', 'MapTooltip', 'Travel Here', 'linkedActions'] as $needle) {
        $runner->assertContains($needle, $locationPage);
    }
    $runner->assertContains('World Map', $nav);
});

$runner->test('Map asset manifest provides safe local fallbacks', function () use ($runner, $manifest): void {
    foreach (['getWorldMapAsset', 'getRegionMapAsset', 'getRegionOverlayAsset', 'getMapPlaceholder', 'getHotspotIcon', '/assets/maps/placeholders/default_map.svg'] as $needle) {
        $runner->assertContains($needle, $manifest);
    }
});

$runner->test('Frontend map types define API payloads', function () use ($runner, $types): void {
    foreach (['WorldRegion', 'WorldLocation', 'UserLocationState', 'WorldMapResponse', 'RegionMapResponse', 'TravelRequest', 'TravelResponse', 'MapRiskSummary'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('Map assets exist locally', function () use ($runner, $root): void {
    foreach ([
        '/frontend/public/assets/maps/world/world_map.webp',
        '/frontend/public/assets/maps/city/main_city_map.webp',
        '/frontend/public/assets/maps/rural/rural_county_map.webp',
        '/frontend/public/assets/maps/forest/forest_hills_map.webp',
        '/frontend/public/assets/maps/shore/shore_beach_sea_map.webp',
        '/frontend/public/assets/maps/docks/docks_map.webp',
        '/frontend/public/assets/maps/industrial/industrial_zone_map.webp',
        '/frontend/public/assets/maps/suburbs/suburbs_map.webp',
        '/frontend/public/assets/maps/old-town/old_town_map.webp',
        '/frontend/public/assets/maps/outskirts/highway_outskirts_map.webp',
        '/frontend/public/assets/maps/placeholders/default_map.svg',
    ] as $path) {
        $runner->assertTrue(is_file($root . $path), 'Missing map asset: ' . $path);
    }
});

$runner->test('Development log documents v0.6', function () use ($runner, $docs): void {
    $runner->assertContains('v0.6 — Game Map & Territories', $docs);
});

exit($runner->finish());

function readFileOrFail(string $path): string
{
    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException("Could not read {$path}");
    }

    return $content;
}
