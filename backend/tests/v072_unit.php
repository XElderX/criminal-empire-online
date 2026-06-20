<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Services\EquipmentSlotService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.2', function () use ($runner): void {
    $runner->assertSame('0.7.2', GameConfig::VERSION);
});

$runner->test('Release title identifies inventory loadout visibility hotfix', function () use ($runner): void {
    $runner->assertContains('Inventory Loadout UX & Equipment Visibility Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('Equipment slots still include visual body and gear slots', function () use ($runner): void {
    $slots = (new EquipmentSlotService())->slots();
    foreach (['head','torso','legs','boots','hands','primary_weapon','sidearm','melee','tool','utility_1','utility_2','bag','armor','disguise'] as $slot) {
        $runner->assertTrue(in_array($slot, $slots, true), "Missing slot {$slot}");
    }
});

exit($runner->finish());
