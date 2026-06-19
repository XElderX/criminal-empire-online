<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.6.1.2', function () use ($runner): void {
    $runner->assertSame('0.6.1.2', GameConfig::VERSION);
});

$runner->test('Release title identifies Dirty Job Boss Support update', function () use ($runner): void {
    $runner->assertContains('Dirty Job Boss Support', GameConfig::RELEASE_TITLE);
});

$runner->test('Boss integration migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/010_v051_boss_character_integration.sql'));
});

exit($runner->finish());
