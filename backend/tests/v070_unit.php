<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Config\ShopConfig;
use App\Services\CarryInventoryService;
use App\Services\EquipmentSlotService;
use App\Services\LoadoutScoreService;
use App\Services\PaginationService;
use App\Services\ShopPaymentService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.4.2', function () use ($runner): void {
    $runner->assertSame('0.7.4.2', GameConfig::VERSION);
});

$runner->test('Release title identifies v0.7 loadout expansion', function () use ($runner): void {
    $runner->assertContains('Recruitment Identity Diversity Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('Equipment slots include body and utility slots', function () use ($runner): void {
    $slots = (new EquipmentSlotService())->slots();
    foreach (['head','torso','legs','boots','hands','primary_weapon','sidearm','melee','tool','utility_1','utility_2','bag','armor','disguise'] as $slot) {
        $runner->assertTrue(in_array($slot, $slots, true), "Missing slot {$slot}");
    }
});

$runner->test('Carry inventory capacity text explains backpack and duffel bonuses', function () use ($runner): void {
    $text = (new CarryInventoryService())->capacityText();
    $runner->assertContains('5 carry units', $text);
    $runner->assertContains('backpack adds +2', $text);
    $runner->assertContains('duffel bag adds +4', $text);
});

$runner->test('Loadout score calculation returns required sliders', function () use ($runner): void {
    $scores = (new LoadoutScoreService())->score([
        ['effects' => json_encode(['stealth_bonus' => 4]), 'item_effects' => json_encode(['evidence_risk_multiplier' => 0.8]), 'visible_illegal' => 0],
        ['effects' => json_encode(['intimidation_bonus' => 7, 'police_suspicion_bonus' => 4]), 'item_effects' => '{}', 'visible_illegal' => 1],
    ]);
    foreach (['stealth','intimidation','protection','carry_capacity','police_suspicion','mobility','evidence_safety','utility'] as $key) {
        $runner->assertTrue(array_key_exists($key, $scores), "Missing score {$key}");
    }
});

$runner->test('Pagination service caps logs at 30', function () use ($runner): void {
    $runner->assertSame(30, (new PaginationService())->perPage(['limit' => 1000]));
});

$runner->test('Shop config supports dirty money payment metadata', function () use ($runner): void {
    $shops = ShopConfig::shops();
    $runner->assertTrue(in_array('dirty_money', $shops['smuggler_pier_dealer']['accepted_payment_types'], true));
    $runner->assertFalse($shops['market_tool_shop']['accepts_dirty_money']);
});

$runner->test('Shop payment service exposes configured payment types', function () use ($runner): void {
    $options = (new ShopPaymentService())->options(['accepted_payment_types_json' => json_encode(['cash', 'dirty_money']), 'is_black_market' => 1]);
    $runner->assertTrue(in_array('dirty_money', $options, true));
});

$runner->test('v0.7 migration and seed exist', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/018_v070_inventory_loadouts_ux.sql'));
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/seeders/018_v070_inventory_loadouts_ux_seed.sql'));
});

exit($runner->finish());
