<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$runner->test('Version is v0.7.4', function () use ($runner): void {
    $runner->assertSame('0.7.4', GameConfig::VERSION);
});

$runner->test('Release title identifies loadout UX carry polish', function () use ($runner): void {
    $runner->assertContains('Global UX, Notifications & Outcome Focus Polish', GameConfig::RELEASE_TITLE);
});

$runner->test('Loadout workspace service returns character-centered fields', function () use ($runner): void {
    $source = readFileOrFail(__DIR__ . '/../app/Services/LoadoutWorkspaceService.php');
    foreach (['characters', 'selected_character', 'owned_items', 'compatibleSlots', 'recommendedSlot', 'currentlyEquippedBy', 'currentlyCarriedBy', 'carryPurpose', 'item_role_guide'] as $needle) {
        $runner->assertContains($needle, $source);
    }
});

$runner->test('Loadout workspace route is registered', function () use ($runner): void {
    $routes = readFileOrFail(__DIR__ . '/../routes/api.php');
    $controller = readFileOrFail(__DIR__ . '/../app/Controllers/ItemController.php');
    $runner->assertContains('/api/loadouts/workspace', $routes);
    $runner->assertContains('loadoutWorkspace', $controller);
    $runner->assertContains('LoadoutWorkspaceService', $controller);
});

$runner->test('Crew loadouts include portrait metadata through presentation service', function () use ($runner): void {
    $service = readFileOrFail(__DIR__ . '/../app/Services/CharacterLoadoutService.php');
    foreach (['portrait_set_key', 'portrait_stage_cache', 'CrewPresentationService', 'portrait_focal_x'] as $needle) {
        $runner->assertContains($needle, $service);
    }
});

$runner->test('Carried inventory rejects equip-only clothing armor and weapons by role', function () use ($runner): void {
    $service = readFileOrFail(__DIR__ . '/../app/Services/CharacterLoadoutService.php');
    foreach (['itemCanBeCarriedAsTaskGear', 'This item belongs in an equipment slot, not carried inventory', 'medical', 'event_unlock', 'vehicle_crime_bonus'] as $needle) {
        $runner->assertContains($needle, $service);
    }
});

$runner->test('v0.7.3 migration and seeder exist', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/020_v073_loadout_ux_carry_inventory_polish.sql'));
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/seeders/020_v073_loadout_ux_carry_inventory_polish_seed.sql'));
});

$runner->test('v0.7.3 migration adds item role and carried-purpose metadata', function () use ($runner): void {
    $migration = readFileOrFail(dirname(__DIR__) . '/database/migrations/020_v073_loadout_ux_carry_inventory_polish.sql');
    foreach (['item_role', 'carry_role', 'is_consumable', 'is_task_item', 'preferred_equipment_slot', 'carried_purpose', 'task_item_state'] as $needle) {
        $runner->assertContains($needle, $migration);
    }
});

$runner->test('v0.7.3 seed distinguishes equipped gear from consumables and carry tools', function () use ($runner): void {
    $seed = readFileOrFail(dirname(__DIR__) . '/database/seeders/020_v073_loadout_ux_carry_inventory_polish_seed.sql');
    foreach (['equip_only', 'consumable', 'carry_tool', 'crime_utility', 'task_item', 'first_aid_kit', 'burner_phone'] as $needle) {
        $runner->assertContains($needle, $seed);
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
