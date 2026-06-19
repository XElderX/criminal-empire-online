<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Services\MapRiskService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.6.5.1', function () use ($runner): void {
    $runner->assertSame('0.6.5.1', GameConfig::VERSION);
});

$runner->test('Release title identifies meaningful travel', function () use ($runner): void {
    $runner->assertContains('Map Shop UX & Navigation Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('Map risk service labels police-heavy zones', function () use ($runner): void {
    $risk = (new MapRiskService())->summarize([
        'heat_level' => 20,
        'police_pressure' => 82,
        'danger_level' => 20,
    ]);

    $runner->assertSame('Police Heavy', $risk['label']);
});

$runner->test('Map risk service labels safe zones', function () use ($runner): void {
    $risk = (new MapRiskService())->summarize([
        'heat_level' => 3,
        'police_pressure' => 5,
        'danger_level' => 4,
    ]);

    $runner->assertSame('Safe', $risk['label']);
});

$runner->test('v0.6 map asset manifest exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__, 2) . '/frontend/src/data/mapAssetManifest.ts'));
});

exit($runner->finish());
