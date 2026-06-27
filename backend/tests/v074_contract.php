<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$provider = readFileOrFail($root . '/frontend/src/components/notifications/NotificationProvider.tsx');
$adapter = readFileOrFail($root . '/frontend/src/components/notifications/outcomeAdapter.ts');
$types = readFileOrFail($root . '/frontend/src/components/notifications/types.ts');
$app = readFileOrFail($root . '/frontend/src/App.tsx');
$apiClient = readFileOrFail($root . '/frontend/src/api/client.ts');
$appTabs = readFileOrFail($root . '/frontend/src/components/ui/AppTabs.tsx');
$uiBits = readFileOrFail($root . '/frontend/src/components/ui/OutcomeBits.tsx');
$css = readFileOrFail($root . '/frontend/src/styles/app.css');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
$apiDocs = readFileOrFail($root . '/backend/docs-api.md');

$runner->test('Notification provider exposes toast drawer bell and outcome overlay', function () use ($runner, $provider): void {
    foreach (['NotificationProvider', 'ToastNotification', 'NotificationBell', 'NotificationDrawer', 'OutcomeFocusOverlay', 'aria-modal', 'Escape', 'ceo:api-feedback'] as $needle) {
        $runner->assertContains($needle, $provider);
    }
});

$runner->test('API client dispatches action feedback events for success and errors', function () use ($runner, $apiClient): void {
    foreach (['emitApiFeedback', 'ceo:api-feedback', 'response.ok', 'method', 'path'] as $needle) {
        $runner->assertContains($needle, $apiClient);
    }
});

$runner->test('Outcome adapter converts API responses into notification reports', function () use ($runner, $adapter): void {
    foreach (['buildOutcomeFromApiResponse', 'outcome_payload', 'notification_payload', 'Quick Crime Report', 'Dirty Job Report', 'Travel Report', 'Purchase Complete', 'Gear Equipped', 'nextActionsFromPath'] as $needle) {
        $runner->assertContains($needle, $adapter);
    }
});

$runner->test('Notification types include all requested categories and priorities', function () use ($runner, $types): void {
    foreach (['success', 'failure', 'warning', 'danger', 'reward', 'heat', 'police', 'injury', 'arrest', 'death', 'level_up', 'item', 'money', 'travel', 'shop', 'tutorial', 'critical'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('App wraps gameplay in NotificationProvider', function () use ($runner, $app): void {
    $runner->assertContains('NotificationProvider', $app);
});

$runner->test('Reusable tab and important-info components exist', function () use ($runner, $appTabs, $uiBits): void {
    foreach (['AppTabs', 'AppTabPanel', 'tab-count-badge', 'InfoHighlight', 'StatDelta', 'WarningCallout', 'NextActionCard'] as $needle) {
        $runner->assertContains($needle, $appTabs . $uiBits);
    }
});

$runner->test('v0.7.4 CSS supports overlays tabs toasts drawers and mobile UX', function () use ($runner, $css): void {
    foreach (['outcome-backdrop', 'outcome-focus-panel', 'toast-stack', 'notification-drawer', 'notification-bell', 'app-tabs', 'loading-skeleton', '@media (max-width: 760px)'] as $needle) {
        $runner->assertContains($needle, $css);
    }
});

$runner->test('Documentation records v0.7.4 global UX notification patch', function () use ($runner, $docs, $apiDocs): void {
    foreach (['v0.7.4 — Global UX, Notifications & Outcome Focus Polish', 'global notification system', 'outcome_payload', 'NotificationProvider'] as $needle) {
        $runner->assertContains($needle, $docs . $apiDocs);
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
