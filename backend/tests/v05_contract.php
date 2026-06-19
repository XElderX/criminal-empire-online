<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail($root . '/backend/database/migrations/009_v05_heat_police_expansion.sql');
$seed = readFileOrFail($root . '/backend/database/seeders/009_v05_heat_police_seed.sql');
$heatService = readFileOrFail($root . '/backend/app/Services/HeatPressureService.php');
$investigation = readFileOrFail($root . '/backend/app/Services/InvestigationService.php');
$boss = readFileOrFail($root . '/backend/app/Services/BossCharacterService.php');
$succession = readFileOrFail($root . '/backend/app/Services/SuccessionService.php');
$crew = readFileOrFail($root . '/backend/app/Services/CrewService.php');
$routes = readFileOrFail($root . '/backend/routes/api.php');
$types = readFileOrFail($root . '/frontend/src/types.ts');
$app = readFileOrFail($root . '/frontend/src/App.tsx');
$heatPage = readFileOrFail($root . '/frontend/src/pages/HeatPolicePage.tsx');
$notice = readFileOrFail($root . '/frontend/src/components/UpdateNoticeModal.tsx');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');

$runner->test('v0.5 migration adds heat, investigation, boss, revenge and update notice tables', function () use ($runner, $migration): void {
    foreach (['heat_logs', 'police_investigations', 'police_events', 'boss_history', 'crew_revenge_events', 'heat_processing_state', 'update_notices', 'user_update_notice_acknowledgements'] as $table) {
        $runner->assertContains($table, $migration);
    }
});

$runner->test('v0.5 migration adds personal/gang/district heat fields', function () use ($runner, $migration): void {
    foreach (['boss_personal_heat', 'gang_heat', 'personal_heat', 'district_heat', 'boss_alive', 'boss_arrested_until', 'sent_away_until', 'revenge_risk'] as $field) {
        $runner->assertContains($field, $migration);
    }
});

$runner->test('v0.5 seed defines heat reduction actions and update notice', function () use ($runner, $seed): void {
    foreach (['lie_low_short', 'lie_low_full_day', 'bribe_contact', 'pay_lawyer', 'destroy_evidence', 'send_crew_away', '0.5.0'] as $needle) {
        $runner->assertContains($needle, $seed);
    }
});

$runner->test('Heat service implements ownership, logs, idle decay, weekly quiet bonus and dismissal relief', function () use ($runner, $heatService): void {
    foreach (['applyHeat', 'recordCrimeHeat', 'processDaily', 'daily_heat_reduced', 'weekly_heat_reduced', 'dismissHeatRelief', 'crew_revenge_events', 'heat_logs'] as $needle) {
        $runner->assertContains($needle, $heatService);
    }
});

$runner->test('Investigation service opens, advances and reduces police pressure', function () use ($runner, $investigation): void {
    foreach (['openOrAdvance', 'advanceOpenInvestigations', 'reducePressure', 'police_investigations', 'police_events', 'arrest_pending'] as $needle) {
        $runner->assertContains($needle, $investigation);
    }
});

$runner->test('Boss and succession services preserve account and choose crew successor', function () use ($runner, $boss, $succession): void {
    foreach (['boss_health', 'boss_status', 'boss_dead_at', 'triggerSuccession', 'bestCandidate', 'boss_successor_member_id'] as $needle) {
        $runner->assertTrue(str_contains($boss . $succession, $needle), "Missing {$needle}");
    }
});

$runner->test('Dismissed high heat crew creates heat relief and revenge risk', function () use ($runner, $crew, $heatService): void {
    $runner->assertContains('dismissHeatRelief', $crew);
    $runner->assertContains('revenge_event_created', $crew);
    $runner->assertContains('revenge_plot', $heatService);
});

$runner->test('v0.5 API routes are exposed', function () use ($runner, $routes): void {
    foreach (['/api/heat', '/api/heat/reduction-options', '/api/heat/reduce', '/api/heat/process-day', '/api/investigations', '/api/boss', '/api/boss/succession', '/api/update-notices/pending', '/api/admin/heat', '/api/admin/investigations'] as $route) {
        $runner->assertContains($route, $routes);
    }
});

$runner->test('Frontend has Heat page, notice modal, and typed heat payloads', function () use ($runner, $types, $app, $heatPage, $notice): void {
    foreach (['HeatOverview', 'BossProfile', 'PoliceInvestigation', 'UpdateNotice'] as $type) {
        $runner->assertContains($type, $types);
    }
    $runner->assertContains('HeatPolicePage', $app);
    $runner->assertContains('UpdateNoticeModal', $app);
    $runner->assertContains('Heat & Police Pressure', $heatPage);
    $runner->assertContains('I understand, continue', $notice);
});

$runner->test('Development log documents v0.5 update', function () use ($runner, $docs): void {
    $runner->assertContains('v0.5 — Heat & Police Pressure Expansion', $docs);
    $runner->assertContains('Dismissed high-heat crew', $docs);
    $runner->assertContains('one-time update notice modal', $docs);
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
