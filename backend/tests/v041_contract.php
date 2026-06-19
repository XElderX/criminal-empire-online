<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail($root . '/backend/database/migrations/008_v041_quick_crimes.sql');
$seed = readFileOrFail($root . '/backend/database/seeders/008_v041_quick_crimes_seed.sql');
$service = readFileOrFail($root . '/backend/app/Services/QuickCrimeService.php');
$requirements = readFileOrFail($root . '/backend/app/Services/ItemRequirementService.php');
$experience = readFileOrFail($root . '/backend/app/Services/ExperienceService.php');
$skills = readFileOrFail($root . '/backend/app/Services/SkillProgressionService.php');
$controller = readFileOrFail($root . '/backend/app/Controllers/QuickCrimeController.php');
$routes = readFileOrFail($root . '/backend/routes/api.php');
$types = readFileOrFail($root . '/frontend/src/types.ts');
$crimesPage = readFileOrFail($root . '/frontend/src/pages/CrimesPage.tsx');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');

$runner->test('v0.4.1 migration adds quick crime and progression tables', function () use ($runner, $migration): void {
    foreach ([
        'quick_crime_templates',
        'quick_crime_runs',
        'quick_crime_cooldowns',
        'quick_crime_preparations',
        'quick_crime_events',
        'quick_crime_run_crew',
        'experience_logs',
        'skill_progression_logs',
        'player_recent_actions',
        'item_tags',
        'item_effects',
    ] as $table) {
        $runner->assertContains($table, $migration);
    }
});

$runner->test('v0.4.1 seed defines balanced quick crimes', function () use ($runner, $seed): void {
    foreach ([
        'Pickpocket Pedestrian',
        'Shoplift Small Goods',
        'Steal Bicycle Parts',
        'Steal Car Lamps',
        'Break Into Parked Car',
        'Small Warehouse Sneak-In',
        'Store Robbery',
        'Low-Value Vehicle Theft',
        'Ask Around for Rumors',
        'Watch Target Street',
    ] as $name) {
        $runner->assertContains($name, $seed);
    }
});

$runner->test('Store robbery and vehicle theft have hard item tags', function () use ($runner, $seed): void {
    $runner->assertContains("JSON_ARRAY('mask')", $seed);
    $runner->assertContains("JSON_ARRAY('blade_weapon','firearm')", $seed);
    $runner->assertContains("JSON_ARRAY('gloves')", $seed);
    $runner->assertContains("JSON_ARRAY('vehicle_tool','lockpick')", $seed);
});

$runner->test('Quick crime service validates requirements, cooldowns and idempotency', function () use ($runner, $service): void {
    foreach ([
        'function prepare',
        'function start',
        'function decide',
        'function resolve',
        'requirementMessages',
        'validateCooldowns',
        'startCooldowns',
        'idempotency_key',
        'resolved = 0',
        'beginTransaction',
        'FOR UPDATE',
    ] as $needle) {
        $runner->assertContains($needle, $service);
    }
});

$runner->test('Requirement service uses item tags and ownership', function () use ($runner, $requirements): void {
    foreach (['item_tags', 'ownedItems', 'ownedWeapons', 'required_all', 'required_any', 'Missing'] as $needle) {
        $runner->assertContains($needle, $requirements);
    }
});

$runner->test('Experience and skill services write logs', function () use ($runner, $experience, $skills): void {
    $runner->assertContains('experience_logs', $experience);
    $runner->assertContains('levelForExperience', $experience);
    $runner->assertContains('skill_progression_logs', $skills);
    $runner->assertContains('maybeImproveCrew', $skills);
    $runner->assertContains('maybeImprovePlayer', $skills);
});

$runner->test('Quick crime routes are exposed', function () use ($runner, $routes): void {
    foreach ([
        '/api/quick-crimes',
        '/api/quick-crimes/{id}/prepare',
        '/api/quick-crimes/{id}/start',
        '/api/quick-crimes/runs/{id}/decision',
        '/api/quick-crimes/history',
        '/api/player/progression',
    ] as $route) {
        $runner->assertContains($route, $routes);
    }
});

$runner->test('Controller maps API to quick crime service', function () use ($runner, $controller): void {
    foreach (['QuickCrimeService', 'function index', 'function prepare', 'function start', 'function decide', 'function resolve', 'function history'] as $needle) {
        $runner->assertContains($needle, $controller);
    }
});

$runner->test('Frontend types define quick crime payloads', function () use ($runner, $types): void {
    foreach (['QuickCrimeTemplate', 'QuickCrimeOverview', 'QuickCrimeRun', 'QuickCrimeEventChoice', 'QuickCrimeResultPayload'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('Crimes page renders quick crimes, cooldowns, requirements and result panels', function () use ($runner, $crimesPage): void {
    foreach (['Explore Leads', 'Quick Crimes & Street Actions', 'Fallback Street Actions', 'QuickCrimeCard', 'QuickRequirementList', 'Cooldown', 'Missing', 'QuickCrimeResultPanel', 'Start quick crime', 'Commit quick action'] as $needle) {
        $runner->assertContains($needle, $crimesPage);
    }
});

$runner->test('Development log documents v0.4.2', function () use ($runner, $docs): void {
    $runner->assertContains('v0.4.2 — Fallback Street Actions', $docs);
    $runner->assertContains('item tags', $docs);
    $runner->assertContains('cooldown', $docs);
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
