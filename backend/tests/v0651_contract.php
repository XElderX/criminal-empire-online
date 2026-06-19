<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$navigation = readFileOrFail($root . '/frontend/src/components/Navigation.tsx');
$shopsPage = readFileOrFail($root . '/frontend/src/pages/ShopsPage.tsx');
$shopItemCard = readFileOrFail($root . '/frontend/src/components/shop/ShopItemCard.tsx');
$mapHotspot = readFileOrFail($root . '/frontend/src/components/map/MapHotspot.tsx');
$css = readFileOrFail($root . '/frontend/src/styles/app.css');
$adminPage = readFileOrFail($root . '/frontend/src/pages/AdminPage.tsx');
$tutorialUpdate = readFileOrFail($root . '/backend/app/Services/TutorialUpdateService.php');
$migration = readFileOrFail($root . '/backend/database/migrations/017_v0651_shop_map_ux_hotfix.sql');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
$apiDocs = readFileOrFail($root . '/backend/docs-api.md');

$runner->test('Shops are not a primary navigation tab anymore', function () use ($runner, $navigation): void {
    $runner->assertFalse(str_contains($navigation, "{ page: 'shops', label: 'Shops' }"));
});

$runner->test('Shops page is map-first with optional shortcuts', function () use ($runner, $shopsPage): void {
    foreach (['Pick a shop icon on the map', 'Open World Map', 'Optional quick access', 'showShortcuts'] as $needle) {
        $runner->assertContains($needle, $shopsPage);
    }
});

$runner->test('Shop item cards use compact thumbnails instead of raw large images', function () use ($runner, $shopItemCard, $css): void {
    foreach (['shop-item-thumb', 'shop-item-card-clean', 'shop-item-description', 'object-fit: cover'] as $needle) {
        $runner->assertContains($needle, $shopItemCard . $css);
    }
});

$runner->test('Map hotspots expose clickable shop markers', function () use ($runner, $mapHotspot, $css): void {
    foreach (['map-shop-marker', 'primary_shop_slug', 'Open', 'has-shop'] as $needle) {
        $runner->assertContains($needle, $mapHotspot . $css);
    }
});

$runner->test('Admin page has sub tabs', function () use ($runner, $adminPage, $css): void {
    foreach (['admin-tabs', 'Players & tools', 'Asset catalog', 'NPC browser', 'Audit log'] as $needle) {
        $runner->assertContains($needle, $adminPage . $css);
    }
});

$runner->test('Tutorial update service does not reopen patch tutorial for completed v0.6.4 users', function () use ($runner, $tutorialUpdate, $migration): void {
    foreach (['TUTORIAL_UPDATE_TRIGGER_VERSION', 'markUpdateNotNeeded', 'versionAtLeast', 'completed_tutorial_version IN'] as $needle) {
        $runner->assertContains($needle, $tutorialUpdate . $migration);
    }
});

$runner->test('Documentation records v0.6.5.1 UX hotfix', function () use ($runner, $docs, $apiDocs): void {
    foreach (['v0.6.5.1 — Map Shop UX & Navigation Hotfix', 'map-first', 'Admin page subtabs', 'World Systems tutorial'] as $needle) {
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
