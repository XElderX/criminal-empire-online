<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail($root . '/backend/database/migrations/012_v061_map_gameplay_integration.sql');
$migrationHotfix = readFileOrFail($root . '/backend/database/migrations/013_v0612_dirty_job_boss_support.sql');
$seed = readFileOrFail($root . '/backend/database/seeders/012_v061_map_gameplay_integration_seed.sql');
$mapContext = readFileOrFail($root . '/backend/app/Services/MapContextService.php');
$localActivity = readFileOrFail($root . '/backend/app/Services/LocalActivityService.php');
$exploration = readFileOrFail($root . '/backend/app/Services/HotspotExplorationService.php');
$risk = readFileOrFail($root . '/backend/app/Services/LocationRiskModifierService.php');
$quick = readFileOrFail($root . '/backend/app/Services/QuickCrimeService.php');
$dirty = readFileOrFail($root . '/backend/app/Services/DirtyJobService.php');
$controller = readFileOrFail($root . '/backend/app/Controllers/WorldMapController.php');
$routes = readFileOrFail($root . '/backend/routes/api.php');
$types = readFileOrFail($root . '/frontend/src/types.ts');
$locationPage = readFileOrFail($root . '/frontend/src/pages/LocationMapPage.tsx');
$crimesPage = readFileOrFail($root . '/frontend/src/pages/CrimesPage.tsx');
$dirtyPage = readFileOrFail($root . '/frontend/src/pages/DirtyJobsPage.tsx');
$activityPanel = readFileOrFail($root . '/frontend/src/components/map/LocalActivityPanel.tsx');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');

$runner->test('v0.6.1 migration adds location gameplay tables', function () use ($runner, $migration): void {
    foreach (['quick_crime_location_rules', 'dirty_job_location_rules', 'local_opportunities', 'location_exploration_logs'] as $table) {
        $runner->assertContains($table, $migration);
    }
});

$runner->test('v0.6.1.2 migration upgrades dirty jobs for boss actors', function () use ($runner, $migrationHotfix): void {
    foreach (['dirty_job_assignments', 'actor_type', 'actor_id', '0.6.1.2'] as $needle) {
        $runner->assertContains($needle, $migrationHotfix);
    }
});

$runner->test('v0.6.1 seed maps quick crimes to real hotspots', function () use ($runner, $seed): void {
    foreach (['container-yard', 'parking-lots', 'basement-bars', 'highway-rest-stop', 'quick_crime_location_rules'] as $needle) {
        $runner->assertContains($needle, $seed);
    }
});

$runner->test('v0.6.1 seed maps dirty jobs to real hotspots', function () use ($runner, $seed): void {
    foreach (['dirty_job_location_rules', 'warehouse_grow_cycle', 'steal_car_lamps', 'private-estates'] as $needle) {
        $runner->assertContains($needle, $seed);
    }
});

$runner->test('Map context and local activity services exist', function () use ($runner, $mapContext, $localActivity, $risk): void {
    $runner->assertContains('class MapContextService', $mapContext);
    $runner->assertContains('class LocalActivityService', $localActivity);
    $runner->assertContains('quickCrimePreview', $localActivity);
    $runner->assertContains('dirtyJobPreview', $localActivity);
    $runner->assertContains('class LocationRiskModifierService', $risk);
});

$runner->test('Hotspot exploration has energy cost and cooldown', function () use ($runner, $exploration): void {
    $runner->assertContains('ENERGY_COST', $exploration);
    $runner->assertContains('COOLDOWN_SECONDS', $exploration);
    $runner->assertContains('local_opportunities', $exploration);
    $runner->assertContains('location_exploration_logs', $exploration);
});

$runner->test('World map controller exposes rich activities and exploration', function () use ($runner, $controller, $routes): void {
    $runner->assertContains('LocalActivityService', $controller);
    $runner->assertContains('HotspotExplorationService', $controller);
    $runner->assertContains('/api/world-map/locations/{slug}/activities', $routes);
    $runner->assertContains('/api/world-map/regions/{slug}/activities', $routes);
    $runner->assertContains('/api/world-map/locations/{slug}/explore', $routes);
});

$runner->test('Quick crimes are location-aware and backend-enforced', function () use ($runner, $quick): void {
    foreach (['quick_crime_location_rules', 'locationRuleForTemplate', 'Travel to', 'reward_multiplier', 'heat_multiplier', 'location_context', 'local_sort_order', '$seenTemplateIds'] as $needle) {
        $runner->assertContains($needle, $quick);
    }
});

$runner->test('Dirty jobs support region and location filters', function () use ($runner, $dirty): void {
    foreach (['dirty_job_location_rules', 'local_region_slug', 'local_location_slug', 'location_context', 'MapContextService'] as $needle) {
        $runner->assertContains($needle, $dirty);
    }
});

$runner->test('Dirty jobs can assign the boss as an actor', function () use ($runner, $dirty, $dirtyPage, $types): void {
    foreach (['function bossActor', "actor_type = 'boss'", 'BossCharacterService', 'The boss is already assigned to another Dirty Job.'] as $needle) {
        $runner->assertContains($needle, $dirty);
    }

    foreach (['member.is_boss', 'assignableMemberIds', 'Boss:', 'actor_type?: \'boss\' | \'crew\''] as $needle) {
        $runner->assertContains($needle, $dirtyPage . $types);
    }
});

$runner->test('Location map page shows local activity panel and explore button', function () use ($runner, $locationPage, $activityPanel): void {
    $runner->assertContains('LocalActivityPanel', $locationPage);
    $runner->assertContains('exploreHotspot', $locationPage);
    $runner->assertContains('Explore Area', $activityPanel);
    $runner->assertContains('Quick Crimes Nearby', $activityPanel);
});

$runner->test('Crimes and Dirty Jobs pages read map query context', function () use ($runner, $crimesPage, $dirtyPage): void {
    $runner->assertContains('locationQuery', $crimesPage);
    $runner->assertContains('region_slug', $crimesPage);
    $runner->assertContains('location_slug', $crimesPage);
    $runner->assertContains('dirtyJobLocationQuery', $dirtyPage);
    $runner->assertContains('Dirty Jobs near', $dirtyPage);
});

$runner->test('Development log documents v0.6.1.2', function () use ($runner, $docs): void {
    $runner->assertContains('v0.6.1.2 — Dirty Job Boss Support', $docs);
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
