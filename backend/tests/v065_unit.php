<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Config\ShopConfig;
use App\Services\ShopCatalogService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.4.2', function () use ($runner): void {
    $runner->assertSame('0.7.4.2', GameConfig::VERSION);
});

$runner->test('Release title identifies map shop UX hotfix', function () use ($runner): void {
    $runner->assertContains('Recruitment Identity Diversity Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('Shop config exposes core shops and catalog items', function () use ($runner): void {
    $shops = ShopConfig::shops();
    $catalog = ShopConfig::catalog();

    foreach (['pawn_row_fence', 'market_tool_shop', 'suburban_garage', 'medical_supply_counter'] as $slug) {
        $runner->assertTrue(isset($shops[$slug]), "Missing shop {$slug}");
    }

    foreach (['work_gloves', 'screwdriver_set', 'lockpick_set', 'vehicle_tools', 'first_aid_kit'] as $itemKey) {
        $runner->assertTrue(isset($catalog[$itemKey]), "Missing catalog item {$itemKey}");
    }
});

$runner->test('Powerful weapons are not enabled in normal shops', function () use ($runner): void {
    $catalog = ShopConfig::catalog();

    $runner->assertSame(false, $catalog['basic_pistol']['enabled']);
    $runner->assertSame('black_market_only', $catalog['basic_pistol']['availability_status']);
    $runner->assertSame(false, $catalog['pump_shotgun']['enabled']);
    $runner->assertSame('future_only', $catalog['pump_shotgun']['availability_status']);
});

$runner->test('Shop catalog normalizes source hints for missing item guidance', function () use ($runner): void {
    $sources = (new ShopCatalogService())->possibleSources('work_gloves');

    $runner->assertGreaterThan(0, count($sources));
    $runner->assertSame('work_gloves', $sources[0]['item_key']);
    $runner->assertTrue(isset($sources[0]['shop_name']));
    $runner->assertTrue(isset($sources[0]['location_label']));
});

$runner->test('Help tips and guide sections include shops', function () use ($runner): void {
    $tips = GameConfig::contextualHelpTips();
    $sections = array_column(GameConfig::guideSections(), 'key');

    $runner->assertTrue(isset($tips['shops']));
    $runner->assertContains('map shops', strtolower($tips['equipment']['body']));
    $runner->assertTrue(in_array('shops', $sections, true));
});

$runner->test('v0.6.5 migration and seed exist', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/016_v065_map_shops_item_availability.sql'));
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/seeders/016_v065_map_shops_item_availability_seed.sql'));
});

exit($runner->finish());
