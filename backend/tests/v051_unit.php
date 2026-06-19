<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.5.1', function () use ($runner): void {
    $runner->assertSame('0.5.1', GameConfig::VERSION);
});

$runner->test('Release title identifies boss character integration', function () use ($runner): void {
    $runner->assertContains('Boss Character Integration', GameConfig::RELEASE_TITLE);
});

$runner->test('Boss integration migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/010_v051_boss_character_integration.sql'));
});

exit($runner->finish());
