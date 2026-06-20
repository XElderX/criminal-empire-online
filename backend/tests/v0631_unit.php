<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.2', function () use ($runner): void {
    $runner->assertSame('0.7.2', GameConfig::VERSION);
});

$runner->test('Release title identifies world tutorial update', function () use ($runner): void {
    $runner->assertContains('Inventory Loadout UX & Equipment Visibility Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('JobService exposes NPC assignment guard methods', function () use ($runner): void {
    $service = readFileOrFail(dirname(__DIR__) . '/app/Services/JobService.php');

    foreach (['requiredNpcAssignmentCount', 'normalizeAssignedMemberIds', 'activeAssignableCrewCount', 'assignmentRequirementMessage'] as $needle) {
        $runner->assertContains($needle, $service);
    }
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
