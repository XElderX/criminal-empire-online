<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail($root . '/backend/database/migrations/006_v04_crimes_expansion.sql');
$seed = readFileOrFail($root . '/backend/database/seeders/007_v04_crimes_expansion_seed.sql');
$service = readFileOrFail($root . '/backend/app/Services/CrimeOpportunityService.php');
$risk = readFileOrFail($root . '/backend/app/Services/CrimeRiskCalculator.php');
$narrative = readFileOrFail($root . '/backend/app/Services/CrimeNarrativeService.php');
$npcAdmin = readFileOrFail($root . '/backend/app/Services/NpcAdminService.php');
$controller = readFileOrFail($root . '/backend/app/Controllers/CrimeController.php');
$adminController = readFileOrFail($root . '/backend/app/Controllers/AdminController.php');
$routes = readFileOrFail($root . '/backend/routes/api.php');
$crimesPage = readFileOrFail($root . '/frontend/src/pages/CrimesPage.tsx');
$adminPage = readFileOrFail($root . '/frontend/src/pages/AdminPage.tsx');
$types = readFileOrFail($root . '/frontend/src/types.ts');
$items = readFileOrFail($root . '/frontend/src/data/itemIconMap.ts');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');

$runner->test('v0.4 migration adds discovery, opportunity, run and NPC memory tables', function () use ($runner, $migration): void {
    foreach ([
        'crime_discovery_locations',
        'crime_v04_templates',
        'crime_opportunities',
        'crime_preparations',
        'crime_runs',
        'crime_events',
        'npc_relationships',
        'npc_timeline_events',
        'npc_status_logs',
    ] as $table) {
        $runner->assertContains($table, $migration);
    }
});

$runner->test('v0.4 migration keeps dead NPC records inspectable', function () use ($runner, $migration): void {
    foreach (['death_category', 'death_game_date', 'death_notes', 'alive', 'status'] as $field) {
        $runner->assertContains($field, $migration);
    }
});

$runner->test('v0.4 seed creates locations, templates, new items and dead NPC example', function () use ($runner, $seed): void {
    foreach (['Iron Glass Bar', 'crime_v04_templates', 'Screwdriver Set', 'Surveillance Kit', 'Old Smoke'] as $needle) {
        $runner->assertContains($needle, $seed);
    }
});

$runner->test('Crime service has discovery, investigation, preparation, execution and decision methods', function () use ($runner, $service): void {
    foreach (['function explore', 'function investigate', 'function prepare', 'function assignCrew', 'function assignEquipment', 'function start', 'function decide'] as $method) {
        $runner->assertContains($method, $service);
    }
});

$runner->test('Crime service is transaction-aware and prevents duplicate reward resolution', function () use ($runner, $service): void {
    $runner->assertContains('beginTransaction', $service);
    $runner->assertContains('FOR UPDATE', $service);
    $runner->assertContains('resolved = 0', $service);
    $runner->assertContains('resolved = 1', $service);
});

$runner->test('Risk calculator uses crew, equipment, heat, police and contact reliability', function () use ($runner, $risk): void {
    foreach (['crewStatAverage', 'police_risk', 'loot_capacity', 'district_police_presence', 'contact_reliability'] as $needle) {
        $runner->assertContains($needle, $risk);
    }
});

$runner->test('Narrative service exposes backend-owned event choices', function () use ($runner, $narrative): void {
    foreach (['police_patrol', 'witness_spotted', 'rival_interference', 'equipment_failure', 'extra_loot'] as $event) {
        $runner->assertContains($event, $narrative);
    }
});

$runner->test('Crime API routes expose v0.4 lifecycle endpoints', function () use ($runner, $routes): void {
    foreach (['/api/crime-locations/{code}/explore', '/api/crime-opportunities/{id}/investigate', '/api/crime-opportunities/{id}/prepare', '/api/crime-opportunities/{id}/start', '/api/crime-runs/{id}/decision'] as $route) {
        $runner->assertContains($route, $routes);
    }
});

$runner->test('Controller maps v0.4 actions to service calls', function () use ($runner, $controller): void {
    foreach (['explore(', 'investigate(', 'prepare(', 'assignCrew(', 'assignEquipment(', 'start(', 'decide('] as $needle) {
        $runner->assertContains($needle, $controller);
    }
});

$runner->test('Admin NPC endpoints and service exist', function () use ($runner, $routes, $adminController, $npcAdmin): void {
    $runner->assertContains('/api/admin/npcs', $routes);
    $runner->assertContains('function npcs', $adminController);
    $runner->assertContains('function npcDetail', $adminController);
    $runner->assertContains('class NpcAdminService', $npcAdmin);
});

$runner->test('Admin NPC service returns dead status, portrait and timeline data', function () use ($runner, $npcAdmin): void {
    foreach (['is_dead', 'portrait', 'timeline', 'relationships', 'crime_involvement', 'status_logs'] as $needle) {
        $runner->assertContains($needle, $npcAdmin);
    }
});

$runner->test('Crimes frontend renders opportunities, locations, preparations, crew and decisions', function () use ($runner, $crimesPage): void {
    foreach (['Explore leads', 'Known opportunities', 'Prepare selected opportunity', 'Decision needed', 'Save crew', 'Save equipment'] as $needle) {
        $runner->assertContains($needle, $crimesPage);
    }
});

$runner->test('Admin frontend renders dead NPC watermark with accessible text', function () use ($runner, $adminPage): void {
    $runner->assertContains('Admin NPC browser', $adminPage);
    $runner->assertContains('DEAD', $adminPage);
    $runner->assertContains('dead-watermark', $adminPage);
});

$runner->test('Frontend types define v0.4 crime and admin NPC payloads', function () use ($runner, $types): void {
    foreach (['CrimeOverview', 'CrimeOpportunity', 'CrimeRun', 'AdminNpcSummary', 'AdminNpcDetailResponse'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('New v0.4 item art is mapped by frontend asset manifest helpers', function () use ($runner, $items): void {
    foreach (['screwdriver_set', 'first_aid_kit', 'surveillance_kit', 'dark_clothing', 'work_uniform', 'vehicle_tools', 'duffel_bag'] as $item) {
        $runner->assertContains($item, $items);
    }
});

$runner->test('Development log documents v0.3.6.5 and v0.4 updates', function () use ($runner, $docs): void {
    $runner->assertContains('v0.3.6.5', $docs);
    $runner->assertContains('v0.4 — Crimes Expansion', $docs);
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
