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

$runner->test('Inventory table existence check avoids SHOW TABLES prepared-statement syntax errors', function () use ($runner): void {
    $serviceSource = file_get_contents(__DIR__ . '/../app/Services/ItemService.php');
    if ($serviceSource === false) {
        $runner->assertTrue(false, 'Unable to read ItemService source.');
    }

    $runner->assertContains('information_schema.tables', $serviceSource);
    $runner->assertFalse(str_contains($serviceSource, 'SHOW TABLES LIKE ?'), 'Legacy SHOW TABLES placeholder query should not remain.');
});

$runner->test('Boss loadouts are supported by the shared character loadout service', function () use ($runner): void {
    $serviceSource = file_get_contents(__DIR__ . '/../app/Services/CharacterLoadoutService.php');
    if ($serviceSource === false) {
        $runner->assertTrue(false, 'Unable to read CharacterLoadoutService source.');
    }

    $runner->assertContains("(? = 'boss' AND equipment.gang_member_id IS NULL)", $serviceSource);
    $runner->assertContains("\$characterType === 'crew' ? \$characterId : null", $serviceSource);
});

$runner->test('Equipment page exposes boss as a selectable loadout target', function () use ($runner): void {
    $pageSource = file_get_contents(__DIR__ . '/../../frontend/src/pages/EquipmentPage.tsx');
    if ($pageSource === false) {
        $runner->assertTrue(false, 'Unable to read EquipmentPage source.');
    }

    $runner->assertContains("type: 'boss'", $pageSource);
    $runner->assertContains('<option value="boss:0">Boss</option>', $pageSource);
    $runner->assertContains('/loadouts/${selectedTarget.type}/${selectedTarget.id}/equip', $pageSource);
});

$runner->test('Boss loadout schema fix migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/019_v0721_boss_loadout_schema_fix.sql'));
});

exit($runner->finish());
