<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Services\BossCharacterService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.3', function () use ($runner): void {
    $runner->assertSame('0.7.3', GameConfig::VERSION);
});

$runner->test('Release title identifies meaningful travel', function () use ($runner): void {
    $runner->assertContains('Loadout UX & Carry Inventory Polish', GameConfig::RELEASE_TITLE);
});

$runner->test('Boss rank labels scale with level', function () use ($runner): void {
    $service = new BossCharacterService();
    $runner->assertSame('Nobody', $service->rankForLevel(1));
    $runner->assertSame('Crew Boss', $service->rankForLevel(4));
    $runner->assertSame('Kingpin', $service->rankForLevel(8));
});

$runner->test('v0.5 migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/009_v05_heat_police_expansion.sql'));
});

$runner->test('v0.5 seed exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/seeders/009_v05_heat_police_seed.sql'));
});

exit($runner->finish());
