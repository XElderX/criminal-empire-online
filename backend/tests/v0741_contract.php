<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$runner->test('Quick crime decision modal CSS and docs are present', function () use ($runner, $root): void {
    $css = readFileOrFail($root . '/frontend/src/styles/app.css');
    $docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
    $apiDocs = readFileOrFail($root . '/backend/docs-api.md');
    foreach (['outcome-decision-panel', 'outcome-decision-choice', 'decision-error', 'v0.7.4.1', '/api/quick-crimes/runs/{id}/decision'] as $needle) {
        $runner->assertContains($needle, $css . $docs . $apiDocs);
    }
});

$runner->test('Notification overlay still preserves accessibility dismiss behavior', function () use ($runner, $root): void {
    $provider = readFileOrFail($root . '/frontend/src/components/notifications/NotificationProvider.tsx');
    foreach (['role="dialog"', 'aria-modal="true"', 'Escape', 'onMouseDown', 'Decision required'] as $needle) {
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
