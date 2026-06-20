<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$routes = readFileOrFail($root . '/backend/routes/api.php');
$migration = readFileOrFail($root . '/backend/database/migrations/018_v070_inventory_loadouts_ux.sql');
$seeder = readFileOrFail($root . '/backend/database/seeders/018_v070_inventory_loadouts_ux_seed.sql');
$navigation = readFileOrFail($root . '/frontend/src/components/Navigation.tsx');
$equipmentPage = readFileOrFail($root . '/frontend/src/pages/EquipmentPage.tsx');
$adminPage = readFileOrFail($root . '/frontend/src/pages/AdminPage.tsx');
$heatPage = readFileOrFail($root . '/frontend/src/pages/HeatPolicePage.tsx');
$dirtyJobsPage = readFileOrFail($root . '/frontend/src/pages/DirtyJobsPage.tsx');
$warehousePage = readFileOrFail($root . '/frontend/src/pages/WarehousePage.tsx');
$shopTransactionService = readFileOrFail($root . '/backend/app/Services/ShopTransactionService.php');
$characterLoadoutService = readFileOrFail($root . '/backend/app/Services/CharacterLoadoutService.php');
$itemEffectService = readFileOrFail($root . '/backend/app/Services/ItemEffectService.php');
$adminLogs = readFileOrFail($root . '/backend/app/Services/AdminLogService.php');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
$apiDocs = readFileOrFail($root . '/backend/docs-api.md');

$runner->test('Navigation is compact and categorized', function () use ($runner, $navigation): void {
    foreach (['NAVIGATION_GROUPS', 'World', 'Crew', 'Management', 'Heat', 'Guide', 'bottom-quick-nav', 'mobile-more-menu'] as $needle) {
        $runner->assertContains($needle, $navigation);
    }
});

$runner->test('Admin log routes and pagination are registered', function () use ($runner, $routes, $adminLogs): void {
    foreach (['/api/admin/logs', '/api/heat/logs', '/api/warehouse/logs', '/api/inventory/logs', 'MAX_PER_PAGE = 30', 'shop_transactions', 'user_travel_logs'] as $needle) {
        $runner->assertContains($needle, $routes . $adminLogs . readFileOrFail(dirname(__DIR__, 2) . '/backend/app/Services/PaginationService.php'));
    }
});

$runner->test('Loadout API routes are registered', function () use ($runner, $routes): void {
    foreach (['/api/loadouts/boss', '/api/loadouts/crew', '/api/loadouts/crew/{id}', '/api/loadouts/{characterType}/{characterId}/equip', '/api/loadouts/{characterType}/{characterId}/carry', '/api/items/effects'] as $needle) {
        $runner->assertContains($needle, $routes);
    }
});

$runner->test('Migration adds item properties, loadout tables, logs, and payment fields', function () use ($runner, $migration): void {
    foreach (['size_class', 'carry_units', 'allowed_slots', 'item_tags', 'item_effects', 'visible_illegal', 'character_loadout_summaries', 'character_carry_items', 'inventory_logs', 'payment_type', 'dirty_money_delta', 'accepted_payment_types_json'] as $needle) {
        $runner->assertContains($needle, $migration);
    }
});

$runner->test('Seeder configures item effects and dirty-money shop payments', function () use ($runner, $seeder): void {
    foreach (['evidence_risk_multiplier', 'witness_identification_multiplier', 'forced_entry_bonus', 'intimidation_bonus', 'carry_capacity_bonus', 'vehicle_crime_bonus', 'dirty_money', 'basic_pistol'] as $needle) {
        $runner->assertContains($needle, $seeder);
    }
});

$runner->test('Inventory page has required subtabs and loadout components', function () use ($runner, $equipmentPage): void {
    foreach (['Boss loadout', 'Crew loadouts', 'Owned gear', 'Warehouse / Storage', 'Item effects', 'Inventory logs', 'CharacterLoadoutPanel', 'CrewTargetSelector'] as $needle) {
        $runner->assertContains($needle, $equipmentPage);
    }
});

$runner->test('Admin, Heat, Dirty Jobs, and Warehouse pages expose subtabs', function () use ($runner, $adminPage, $heatPage, $dirtyJobsPage, $warehousePage): void {
    foreach (['Shops', 'Economy', 'Investigations', 'Logs'] as $needle) $runner->assertContains($needle, $adminPage);
    foreach (['Boss', 'Crew', 'Reduce Heat', 'Heat Logs'] as $needle) $runner->assertContains($needle, $heatPage);
    foreach (['Available Jobs', 'Active Jobs', 'Awaiting Decision', 'Crew Assignments', 'Local / Map Jobs'] as $needle) $runner->assertContains($needle, $dirtyJobsPage);
    foreach (['Stored Items', 'Contraband', 'Vehicles / Parts', 'Transfers', 'Storage Logs'] as $needle) $runner->assertContains($needle, $warehousePage);
});

$runner->test('Loadout service validates slots, ownership, capacity, and one-item rules', function () use ($runner, $characterLoadoutService): void {
    foreach (['validateCrewMember', 'lockItem', 'Item does not match that equipment slot', 'Broken item cannot be equipped', 'Carry capacity exceeded', 'LoadoutScoreService', 'LoadoutPenaltyService'] as $needle) {
        $runner->assertContains($needle, $characterLoadoutService);
    }
});

$runner->test('Item effects and shop payment services cover balancing rules', function () use ($runner, $itemEffectService, $shopTransactionService): void {
    foreach (['stealth_bonus', 'intimidation_bonus', 'police_suspicion_bonus', 'evidence_risk_multiplier', 'payment_type', 'dirty_money', 'Legal shop rejects dirty money', 'DirtyMoneyPaymentService'] as $needle) {
        $runner->assertContains($needle, $itemEffectService . $shopTransactionService . readFileOrFail(dirname(__DIR__, 2) . '/backend/app/Services/ShopPaymentService.php'));
    }
});

$runner->test('Documentation records v0.7 update', function () use ($runner, $docs, $apiDocs): void {
    foreach (['v0.7.2 — Inventory Loadout UX & Equipment Visibility Hotfix', 'compact categorized navigation', 'equipment slots', 'dirty-money payment', 'GET /api/loadouts/boss'] as $needle) {
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
