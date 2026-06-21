<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$navigation = readFileOrFail($root . '/frontend/src/components/navigation/NavigationGroup.tsx');
$equipmentSlotGrid = readFileOrFail($root . '/frontend/src/components/inventory/EquipmentSlotGrid.tsx');
$characterLoadoutPanel = readFileOrFail($root . '/frontend/src/components/inventory/CharacterLoadoutPanel.tsx');
$warehousePage = readFileOrFail($root . '/frontend/src/pages/WarehousePage.tsx');
$worldMapPage = readFileOrFail($root . '/frontend/src/pages/WorldMapPage.tsx');
$css = readFileOrFail($root . '/frontend/src/styles/app.css');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');

$runner->test('Navigation groups use dropdown menus instead of forced-open details', function () use ($runner, $navigation): void {
    foreach (['nav-group-trigger', 'aria-haspopup="menu"', 'nav-group-menu'] as $needle) {
        $runner->assertContains($needle, $navigation);
    }
    $runner->assertFalse(str_contains($navigation, '<details'), 'Navigation should not render always-open details groups.');
});

$runner->test('Inventory loadout has board layout metadata and silhouette markup', function () use ($runner, $equipmentSlotGrid, $characterLoadoutPanel, $css): void {
    foreach (['loadout-board', 'loadout-silhouette', 'slot-head', 'slot-primary', 'slot-sidearm', 'loadout-workspace', 'loadout-side-panel'] as $needle) {
        $runner->assertContains($needle, $equipmentSlotGrid . $characterLoadoutPanel . $css);
    }
});

$runner->test('Warehouse workspace gates content by selected subtab', function () use ($runner, $warehousePage): void {
    foreach (["activeTab === 'overview'", "activeTab === 'stored'", "activeTab === 'contraband'", "activeTab === 'vehicles'", "activeTab === 'transfers'", "activeTab === 'security'", "activeTab === 'logs'"] as $needle) {
        $runner->assertContains($needle, $warehousePage);
    }
});

$runner->test('World map uses compact region dock', function () use ($runner, $worldMapPage, $css): void {
    foreach (['map-region-dock', 'compact-region-grid', 'world-map-command-grid'] as $needle) {
        $runner->assertContains($needle, $worldMapPage . $css);
    }
});

$runner->test('Documentation records v0.7.2 cleanup', function () use ($runner, $docs): void {
    foreach (['v0.7.3 — Loadout UX & Carry Inventory Polish', 'Inventory / Loadouts page', 'WarehousePage', 'World Map page'] as $needle) {
        $runner->assertContains($needle, $docs);
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
