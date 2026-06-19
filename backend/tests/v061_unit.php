<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Services\LocationRiskModifierService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.6.1.1', function () use ($runner): void {
    $runner->assertSame('0.6.1.1', GameConfig::VERSION);
});

$runner->test('Release title identifies crimes tab sql hotfix', function () use ($runner): void {
    $runner->assertContains('Crimes Tab SQL Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('Location modifiers stay inside safe bounds', function () use ($runner): void {
    $modifiers = (new LocationRiskModifierService())->forLocation([
        'heat_level' => 80,
        'police_pressure' => 90,
        'danger_level' => 95,
    ]);

    $runner->assertTrue($modifiers['reward_multiplier'] <= 1.35);
    $runner->assertTrue($modifiers['heat_multiplier'] <= 1.50);
    $runner->assertTrue($modifiers['police_risk_multiplier'] <= 1.75);
    $runner->assertTrue($modifiers['danger_multiplier'] <= 1.75);
});

$runner->test('v0.6.1 migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/012_v061_map_gameplay_integration.sql'));
});

$runner->test('v0.6.1 seeder exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/seeders/012_v061_map_gameplay_integration_seed.sql'));
});

exit($runner->finish());
