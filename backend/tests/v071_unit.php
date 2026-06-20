<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.1', function () use ($runner): void {
    $runner->assertSame('0.7.1', GameConfig::VERSION);
});

$runner->test('Release title identifies UX cleanup patch', function () use ($runner): void {
    $runner->assertContains('UX Cleanup & Loadout Screen Polish', GameConfig::RELEASE_TITLE);
});

exit($runner->finish());
