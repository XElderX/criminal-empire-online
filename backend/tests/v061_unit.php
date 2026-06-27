<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Services\LocationRiskModifierService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.4.2', function () use ($runner): void {
    $runner->assertSame('0.7.4.2', GameConfig::VERSION);
});

$runner->test('Release title identifies meaningful travel', function () use ($runner): void {
    $runner->assertContains('Recruitment Identity Diversity Hotfix', GameConfig::RELEASE_TITLE);
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

$runner->test('v0.6.1.2 migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/013_v0612_dirty_job_boss_support.sql'));
});

exit($runner->finish());
