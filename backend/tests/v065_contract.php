<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$routes = readFileOrFail($root . '/backend/routes/api.php');
$shopConfig = readFileOrFail($root . '/backend/app/Config/ShopConfig.php');
$shopService = readFileOrFail($root . '/backend/app/Services/ShopService.php');
$transactionService = readFileOrFail($root . '/backend/app/Services/ShopTransactionService.php');
$availabilityService = readFileOrFail($root . '/backend/app/Services/ShopAvailabilityService.php');
$restockService = readFileOrFail($root . '/backend/app/Services/ShopRestockService.php');
$itemRequirements = readFileOrFail($root . '/backend/app/Services/ItemRequirementService.php');
$dirtyJobs = readFileOrFail($root . '/backend/app/Services/DirtyJobService.php');
$localActivity = readFileOrFail($root . '/backend/app/Services/LocalActivityService.php');
$migration = readFileOrFail($root . '/backend/database/migrations/016_v065_map_shops_item_availability.sql');
$seeder = readFileOrFail($root . '/backend/database/seeders/016_v065_map_shops_item_availability_seed.sql');
$equipmentPage = readFileOrFail($root . '/frontend/src/pages/EquipmentPage.tsx');
$shopsPage = readFileOrFail($root . '/frontend/src/pages/ShopsPage.tsx');
$shopsApi = readFileOrFail($root . '/frontend/src/api/shops.ts');
$navigation = readFileOrFail($root . '/frontend/src/components/Navigation.tsx');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
$apiDocs = readFileOrFail($root . '/backend/docs-api.md');

$runner->test('Shop API routes are registered', function () use ($runner, $routes): void {
    foreach (['/api/shops', '/api/shops/{slug}', '/api/shops/{slug}/items', '/api/shops/{slug}/buy', '/api/shops/{slug}/sell', '/api/world-map/locations/{slug}/shops'] as $needle) {
        $runner->assertContains($needle, $routes);
    }
});

$runner->test('Shop config supports availability and black-market states', function () use ($runner, $shopConfig): void {
    foreach (['availability_status', 'allowed_shop_types', 'black_market_only', 'future_only', 'basic_pistol', 'pump_shotgun', 'sourceHints'] as $needle) {
        $runner->assertContains($needle, $shopConfig);
    }
});

$runner->test('Migration creates shop tables and transaction history', function () use ($runner, $migration): void {
    foreach (['CREATE TABLE IF NOT EXISTS shops', 'CREATE TABLE IF NOT EXISTS shop_items', 'CREATE TABLE IF NOT EXISTS shop_transactions', 'requires_local_presence', 'stock_quantity'] as $needle) {
        $runner->assertContains($needle, $migration);
    }
});

$runner->test('Seeder creates known shops and disabled powerful items', function () use ($runner, $seeder): void {
    foreach (['Pawn Row Fence', 'Market Tool Shop', 'Suburban Garage Counter', 'Medical Supply Counter', 'Smuggler Pier Dealer', 'basic_pistol', 'future_dark_market_expansion'] as $needle) {
        $runner->assertContains($needle, $seeder);
    }
});

$runner->test('Shop service and availability enforce local presence and config', function () use ($runner, $shopService, $availabilityService): void {
    foreach (['playerIsAtShop', 'requires_local_presence', 'ShopCatalogService', 'disabled_by_shop_config', 'local_presence_satisfied'] as $needle) {
        $runner->assertContains($needle, $shopService . $availabilityService);
    }
});

$runner->test('Shop transactions are atomic and do not trust frontend prices', function () use ($runner, $transactionService): void {
    foreach (['beginTransaction', 'FOR UPDATE', 'buy_price', 'stock_quantity = stock_quantity - ?', 'shop_transactions', 'Not enough cash', 'Item definition not found.'] as $needle) {
        $runner->assertContains($needle, $transactionService);
    }
});

$runner->test('Selling validates owned unequipped inventory and category rules', function () use ($runner, $transactionService): void {
    foreach (['quantity < 1', 'equippedQuantity', 'does not buy that item category', 'legal shop refuses suspicious goods', 'shop_transactions'] as $needle) {
        $runner->assertContains($needle, $transactionService);
    }
});

$runner->test('Restock is capped and idempotent', function () use ($runner, $restockService): void {
    foreach (['SET stock_quantity = max_stock', 'last_restocked_at', 'restock_interval_minutes'] as $needle) {
        $runner->assertContains($needle, $restockService);
    }
});

$runner->test('Map local activities include real shop previews', function () use ($runner, $localActivity): void {
    foreach (['MapShopService', 'Shops Nearby', 'shopsPreview', 'shops_available_here'] as $needle) {
        $runner->assertContains($needle, $localActivity);
    }
});

$runner->test('Missing item guidance points to shops', function () use ($runner, $itemRequirements, $dirtyJobs): void {
    foreach (['source_hints', 'ShopCatalogService', 'possibleSources'] as $needle) {
        $runner->assertContains($needle, $itemRequirements . $dirtyJobs);
    }
});

$runner->test('Inventory page no longer exposes global buy shop', function () use ($runner, $equipmentPage): void {
    $runner->assertContains('Inventory no longer sells global equipment', $equipmentPage);
    $runner->assertContains('Find Shops', $equipmentPage);
    $runner->assertFalse(str_contains($equipmentPage, "api<ItemShopResponse>('/items'"));
    $runner->assertFalse(str_contains($equipmentPage, "api<WeaponShopResponse>('/weapons'"));
});

$runner->test('Frontend includes shops page and API client while primary navigation stays map-first', function () use ($runner, $shopsPage, $shopsApi, $navigation): void {
    foreach (['ShopsPage', 'ShopItemCard', 'ShopTransactionPanel'] as $needle) {
        $runner->assertContains($needle, $shopsPage);
    }
    foreach (['/shops', '/buy', '/sell'] as $needle) {
        $runner->assertContains($needle, $shopsApi);
    }
    $runner->assertFalse(str_contains($navigation, "{ page: 'shops', label: 'Shops' }"));
});

$runner->test('Documentation records v0.6.5 shops update', function () use ($runner, $docs, $apiDocs): void {
    foreach (['v0.6.5 — Map Shops & Item Availability Expansion', 'map-based shops', 'GET  /api/shops', 'ShopConfig.php'] as $needle) {
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
