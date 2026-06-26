<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.4.1', function () use ($runner): void {
    $runner->assertSame('0.7.4.1', GameConfig::VERSION);
});

$runner->test('Release title identifies shop UX hotfix', function () use ($runner): void {
    $runner->assertContains('Global UX, Notifications & Outcome Focus Polish', GameConfig::RELEASE_TITLE);
});

$runner->test('Tutorial content version remains stable for patch release', function () use ($runner): void {
    $runner->assertSame('0.6.5', GameConfig::TUTORIAL_VERSION);
    $runner->assertSame('0.6.4', GameConfig::TUTORIAL_UPDATE_TRIGGER_VERSION);
});

$runner->test('v0.7 migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/017_v0651_shop_map_ux_hotfix.sql'));
});

exit($runner->finish());
