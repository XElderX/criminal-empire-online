<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Services\OutcomePayloadService;
use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$runner->test('Version is v0.7.4', function () use ($runner): void {
    $runner->assertSame('0.7.4', GameConfig::VERSION);
});

$runner->test('Release title identifies global UX outcome focus polish', function () use ($runner): void {
    $runner->assertContains('Global UX, Notifications & Outcome Focus Polish', GameConfig::RELEASE_TITLE);
});

$runner->test('Outcome payload service creates structured action payloads', function () use ($runner): void {
    $payload = (new OutcomePayloadService())->action('Test', 'Action title', 'Action message', 'reward', 'high', ['cash' => 25, 'heat' => 2], [['label' => 'Next', 'description' => 'Do next thing.']]);
    foreach (['type', 'priority', 'title', 'message', 'source', 'badges', 'sections', 'next_actions'] as $key) {
        $runner->assertTrue(array_key_exists($key, $payload), "Missing {$key}");
    }
    $runner->assertSame('high', $payload['priority']);
});

$runner->test('Outcome payload service supports crimes dirty jobs and travel', function () use ($runner): void {
    $service = new OutcomePayloadService();
    $crime = $service->crime('Quick Crime', ['outcome' => 'success', 'cash_reward' => 50, 'heat_gained' => 4], 'Resolved.');
    $dirty = $service->dirtyJob(['outcome' => 'partial_success', 'cash_reward' => 20, 'dirty_cash_reward' => 30, 'heat_gained' => 5]);
    $travel = $service->travel(['message' => 'Arrived.', 'event' => ['title' => 'Checkpoint', 'description' => 'Police delay.'], 'costs' => ['cash' => 5, 'energy' => 2], 'heatChange' => 1]);
    $runner->assertSame('Crimes', $crime['source']);
    $runner->assertSame('Dirty Jobs', $dirty['source']);
    $runner->assertSame('World Map', $travel['source']);
});

$runner->test('Core services include outcome payloads for important actions', function () use ($runner): void {
    foreach ([
        'app/Services/QuickCrimeService.php',
        'app/Services/DirtyJobService.php',
        'app/Services/TravelService.php',
        'app/Services/ShopTransactionService.php',
        'app/Services/CharacterLoadoutService.php',
        'app/Services/CrimeService.php',
        'app/Services/JobService.php',
    ] as $file) {
        $source = readFileOrFail(dirname(__DIR__) . '/' . $file);
        $runner->assertContains('outcome_payload', $source);
    }
});

$runner->test('No persistent notification migration is required for v0.7.4', function () use ($runner): void {
    $routes = readFileOrFail(dirname(__DIR__) . '/routes/api.php');
    $runner->assertContains('/api/admin/logs', $routes);
    $runner->assertContains('/api/heat/logs', $routes);
    $runner->assertTrue(is_file(dirname(__DIR__) . '/app/Services/OutcomePayloadService.php'));
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
