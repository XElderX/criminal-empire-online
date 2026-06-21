<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use App\Services\TravelRiskService;
use Tests\TestRunner;

$runner = new TestRunner();

$runner->test('Version is v0.7.3', function () use ($runner): void {
    $runner->assertSame('0.7.3', GameConfig::VERSION);
});

$runner->test('Release title identifies meaningful travel', function () use ($runner): void {
    $runner->assertContains('Loadout UX & Carry Inventory Polish', GameConfig::RELEASE_TITLE);
});

$runner->test('Travel risk service recognizes supported route types', function () use ($runner): void {
    foreach (['cheap', 'fast', 'low_profile', 'back_roads'] as $routeType) {
        $runner->assertTrue(in_array($routeType, TravelRiskService::ROUTE_TYPES, true));
    }
});

$runner->test('Travel risk service normalizes unknown routes to cheap', function () use ($runner): void {
    $service = new TravelRiskService();
    $runner->assertSame('cheap', $service->normalizeRouteType('invalid-route', ['region_type' => 'city']));
});

$runner->test('Travel risk service keeps back roads for rural style regions', function () use ($runner): void {
    $service = new TravelRiskService();
    $runner->assertSame('back_roads', $service->normalizeRouteType('back_roads', ['region_type' => 'rural']));
});

$runner->test('Travel risk service rejects back roads in dense city by falling back', function () use ($runner): void {
    $service = new TravelRiskService();
    $runner->assertSame('cheap', $service->normalizeRouteType('back_roads', ['region_type' => 'city']));
});

$runner->test('v0.6.3 migration exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/migrations/014_v063_meaningful_travel.sql'));
});

$runner->test('v0.6.3 seed exists', function () use ($runner): void {
    $runner->assertTrue(is_file(dirname(__DIR__) . '/database/seeders/014_v063_meaningful_travel_seed.sql'));
});

exit($runner->finish());
