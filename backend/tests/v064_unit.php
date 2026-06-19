<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.6.4', function () use ($runner): void {
    $runner->assertSame('0.6.4', GameConfig::VERSION);
});

$runner->test('Release title identifies world tutorial update', function () use ($runner): void {
    $runner->assertContains('World Tutorial & Player Guidance Update', GameConfig::RELEASE_TITLE);
});

$runner->test('Full tutorial contains twenty guided steps', function () use ($runner): void {
    $steps = GameConfig::tutorialSteps(GameConfig::TUTORIAL_KEY_FULL);
    $runner->assertSame(20, count($steps));
    $runner->assertSame('welcome_riverdale', $steps[0]['code']);
    $runner->assertSame('finish_world_tutorial', $steps[19]['code']);
});

$runner->test('World systems update tutorial is short and separate', function () use ($runner): void {
    $steps = GameConfig::tutorialSteps(GameConfig::TUTORIAL_KEY_UPDATE);
    $runner->assertSame(7, count($steps));
    $runner->assertSame('update_open_world_map', $steps[0]['code']);
    $runner->assertSame('update_finish', $steps[6]['code']);
});

$runner->test('Tutorial uses multiple objective types', function () use ($runner): void {
    $types = array_unique(array_column(GameConfig::tutorialSteps(), 'objective_type'));

    foreach (['view_page', 'travel_to_location', 'explore_hotspot', 'complete_quick_crime', 'hire_crew', 'view_heat_page'] as $type) {
        $runner->assertTrue(in_array($type, $types, true), "Missing objective type {$type}");
    }
});

$runner->test('Contextual help tips cover important pages', function () use ($runner): void {
    $tips = GameConfig::contextualHelpTips();

    foreach (['dashboard', 'world_map', 'crimes', 'dirty_jobs', 'recruitment', 'crew', 'equipment', 'warehouse', 'territories', 'heat'] as $key) {
        $runner->assertTrue(isset($tips[$key]), "Missing help tip {$key}");
    }
});

$runner->test('Guide sections explain world systems', function () use ($runner): void {
    $keys = array_column(GameConfig::guideSections(), 'key');

    foreach (['beginner_path', 'world_map', 'travel', 'quick_crimes', 'dirty_jobs', 'crew', 'heat_police', 'territories', 'warehouse', 'progression'] as $key) {
        $runner->assertTrue(in_array($key, $keys, true), "Missing guide section {$key}");
    }
});

$runner->test('v0.6.4 migration and seed exist', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/015_v064_world_tutorial_update.sql'));
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/seeders/015_v064_world_tutorial_seed.sql'));
});

exit($runner->finish());
