<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$equipmentPage = readFileOrFail($root . '/frontend/src/pages/EquipmentPage.tsx');
$builder = readFileOrFail($root . '/frontend/src/components/inventory/LoadoutBuilderTab.tsx');
$selector = readFileOrFail($root . '/frontend/src/components/inventory/LoadoutCharacterSelector.tsx');
$selectedPanel = readFileOrFail($root . '/frontend/src/components/inventory/SelectedCharacterLoadoutPanel.tsx');
$itemPool = readFileOrFail($root . '/frontend/src/components/inventory/LoadoutOwnedItemPool.tsx');
$slotGrid = readFileOrFail($root . '/frontend/src/components/inventory/EquipmentSlotGrid.tsx');
$carryGrid = readFileOrFail($root . '/frontend/src/components/inventory/CarryInventoryGrid.tsx');
$tabs = readFileOrFail($root . '/frontend/src/components/inventory/InventoryTabs.tsx');
$types = readFileOrFail($root . '/frontend/src/types.ts');
$css = readFileOrFail($root . '/frontend/src/styles/app.css');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
$apiDocs = readFileOrFail($root . '/backend/docs-api.md');

$runner->test('Inventory default tab is Loadout Builder', function () use ($runner, $equipmentPage, $tabs): void {
    $runner->assertContains("useState<InventoryTab>('loadout')", $equipmentPage);
    $runner->assertContains("['loadout', 'Loadout Builder']", $tabs);
    $runner->assertFalse(str_contains($tabs, "'boss'"), 'Old separate Boss tab should no longer be the primary workflow.');
});

$runner->test('Loadout Builder combines selector panel slots carried items and owned pool', function () use ($runner, $builder): void {
    foreach (['LoadoutCharacterSelector', 'SelectedCharacterLoadoutPanel', 'LoadoutOwnedItemPool', 'onEquipCarried', 'onStoreCarried'] as $needle) {
        $runner->assertContains($needle, $builder);
    }
});

$runner->test('Crew portraits are visible directly inside loadout management', function () use ($runner, $selector, $selectedPanel): void {
    foreach (['CrewPortrait', 'portraitKey', 'display_name', 'Health', 'Heat'] as $needle) {
        $runner->assertContains($needle, $selector . $selectedPanel);
    }
});

$runner->test('Owned item pool exposes compatibility filters and selected-slot workflow', function () use ($runner, $itemPool): void {
    foreach (['CompatibleItemCard', 'Selected slot', 'compatibleSlots', 'recommendedSlot', 'unavailableReason', 'currentlyEquippedBy', 'currentlyCarriedBy'] as $needle) {
        $runner->assertContains($needle, $itemPool);
    }
});

$runner->test('Equip unequip carry and remove-store flow is available in one workspace', function () use ($runner, $equipmentPage, $itemPool, $slotGrid, $carryGrid): void {
    foreach (['/loadouts/${selectedTarget.type}/${selectedTarget.id}/equip', '/unequip', '/carry', '/drop-or-store', 'Click to filter compatible owned items', 'Remove / store'] as $needle) {
        $runner->assertContains($needle, $equipmentPage . $itemPool . $slotGrid . $carryGrid);
    }
});

$runner->test('Carried inventory copy explains task item purpose', function () use ($runner, $carryGrid, $equipmentPage, $itemPool): void {
    foreach (['consumables, tools, task items', 'Carry purpose', 'Carry for tasks', 'Task/utility item'] as $needle) {
        $runner->assertContains($needle, $carryGrid . $equipmentPage . $itemPool);
    }
});

$runner->test('Workspace TypeScript types include compatibility and portrait summaries', function () use ($runner, $types): void {
    foreach (['LoadoutCharacterSummary', 'LoadoutWorkspaceItem', 'LoadoutWorkspaceResponse', 'canEquip', 'canCarry', 'compatibleSlots', 'carryPurpose'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('v0.7.3 CSS contains portrait-driven builder layout classes', function () use ($runner, $css): void {
    foreach (['loadout-character-card', 'selected-character-dossier', 'loadout-builder-grid', 'loadout-owned-grid', 'compatible-item-card', 'carry-purpose-note'] as $needle) {
        $runner->assertContains($needle, $css);
    }
});

$runner->test('Documentation records v0.7.3 loadout builder patch', function () use ($runner, $docs, $apiDocs): void {
    foreach (['v0.7.3 — Loadout UX & Carry Inventory Polish', 'Loadout Builder', 'GET /api/loadouts/workspace', 'carried inventory'] as $needle) {
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
