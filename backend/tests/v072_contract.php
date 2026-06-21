<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$equipmentPage = readFileOrFail($root . '/frontend/src/pages/EquipmentPage.tsx');
$equipmentSlotGrid = readFileOrFail($root . '/frontend/src/components/inventory/EquipmentSlotGrid.tsx');
$carryGrid = readFileOrFail($root . '/frontend/src/components/inventory/CarryInventoryGrid.tsx');
$heatPage = readFileOrFail($root . '/frontend/src/pages/HeatPolicePage.tsx');
$characterLoadoutService = readFileOrFail($root . '/backend/app/Services/CharacterLoadoutService.php');
$itemService = readFileOrFail($root . '/backend/app/Services/ItemService.php');
$css = readFileOrFail($root . '/frontend/src/styles/app.css');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');

$runner->test('Owned items no longer render raw compact table before cards', function () use ($runner, $equipmentPage): void {
    $runner->assertFalse(str_contains($equipmentPage, '<OwnedItemTable'), 'Owned items should use the polished gear-card layout.');
    foreach (['OwnedGearCard', 'selected-crew-banner', 'CrewTargetSelector', 'Equip to ${actionLabel}', 'Carry with ${actionLabel}'] as $needle) {
        $runner->assertContains($needle, $equipmentPage);
    }
});

$runner->test('Effects are normalized before rendering badges', function () use ($runner, $equipmentPage, $itemService): void {
    foreach (['normalizeEffects', 'Number.isNaN(Number(key))', "item['item_effects'] =", "item['allowed_slots'] ="] as $needle) {
        $runner->assertContains($needle, $equipmentPage . $itemService);
    }
});

$runner->test('Loadout slots and carried items render images for equipped gear', function () use ($runner, $equipmentSlotGrid, $carryGrid, $css): void {
    foreach (['slot-item-image', 'getItemIcon', 'visual-filled', 'visual-carry-card', 'owned-gear-image-frame'] as $needle) {
        $runner->assertContains($needle, $equipmentSlotGrid . $carryGrid . $css);
    }
});

$runner->test('Loadout service returns item and weapon equipment for visual slots', function () use ($runner, $characterLoadoutService): void {
    foreach (['asset_type', 'LEFT JOIN weapons', 'normalizeWeaponSlot', 'primary_weapon', 'sidearm', 'equippedQuantity'] as $needle) {
        $runner->assertContains($needle, $characterLoadoutService);
    }
});

$runner->test('Heat page renders one active subtab instead of every panel and log wall', function () use ($runner, $heatPage): void {
    foreach (["activeTab === 'overview'", "activeTab === 'logs'", 'HeatLogsTab', 'compact-log-list'] as $needle) {
        $runner->assertContains($needle, $heatPage);
    }
});

$runner->test('Documentation records v0.7.2 hotfix', function () use ($runner, $docs): void {
    foreach (['v0.7.3 — Loadout UX & Carry Inventory Polish', 'owned gear cards', 'equipped items now visually appear', 'Heat & Police'] as $needle) {
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
