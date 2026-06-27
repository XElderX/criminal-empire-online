<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$runner->test('Version is v0.7.4.1', function () use ($runner): void {
    $runner->assertSame('0.7.4.1', GameConfig::VERSION);
    $runner->assertContains('Quick Crime Decision Modal Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('Quick crime decision outcome uses event title and modal language', function () use ($runner, $root): void {
    $service = readFileOrFail($root . '/backend/app/Services/QuickCrimeService.php');
    $runner->assertContains('(string) ($event[\'title\'] ?? \'Quick Crime Event\')', $service);
    $runner->assertContains('Choose in modal', $service);
    $runner->assertContains('Decision required', $service);
});

$runner->test('Notification overlay can resolve quick crime decisions directly', function () use ($runner, $root): void {
    $provider = readFileOrFail($root . '/frontend/src/components/notifications/NotificationProvider.tsx');
    foreach (['quickCrimeDecisionContext', 'outcome-decision-panel', '/quick-crimes/runs/${quickDecision.runId}/decision', 'decision_code', 'Decide later'] as $needle) {
        $runner->assertContains($needle, $provider);
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
