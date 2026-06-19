<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Services\ExperienceService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.6.5.1', function () use ($runner): void {
    $runner->assertSame('0.6.5.1', GameConfig::VERSION);
});

$runner->test('Release title identifies meaningful travel', function () use ($runner): void {
    $runner->assertContains('Map Shop UX & Navigation Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('Experience level curve increases predictably', function () use ($runner): void {
    $service = new ExperienceService();

    $runner->assertSame(1, $service->levelForExperience(0));
    $runner->assertSame(2, $service->levelForExperience(100));
    $runner->assertSame(3, $service->levelForExperience(400));
    $runner->assertSame(4, $service->levelForExperience(900));
});

$runner->test('Quick crime migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/008_v041_quick_crimes.sql'));
});

$runner->test('Quick crime seed exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/seeders/008_v041_quick_crimes_seed.sql'));
});

$runner->test('Quick crime services exist', function () use ($runner): void {
    foreach (['QuickCrimeService.php', 'ItemRequirementService.php', 'ExperienceService.php', 'SkillProgressionService.php'] as $file) {
        $runner->assertTrue(is_file(dirname(__DIR__) . '/app/Services/' . $file), 'Missing service ' . $file);
    }
});

exit($runner->finish());
