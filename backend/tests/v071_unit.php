<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.4.2', function () use ($runner): void {
    $runner->assertSame('0.7.4.2', GameConfig::VERSION);
});

$runner->test('Release title identifies UX cleanup patch', function () use ($runner): void {
    $runner->assertContains('Recruitment Identity Diversity Hotfix', GameConfig::RELEASE_TITLE);
});

exit($runner->finish());
