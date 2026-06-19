<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail($root . '/backend/database/migrations/014_v063_meaningful_travel.sql');
$seed = readFileOrFail($root . '/backend/database/seeders/014_v063_meaningful_travel_seed.sql');
$travel = readFileOrFail($root . '/backend/app/Services/TravelService.php');
$travelRisk = readFileOrFail($root . '/backend/app/Services/TravelRiskService.php');
$travelEvent = readFileOrFail($root . '/backend/app/Services/TravelEventService.php');
$presence = readFileOrFail($root . '/backend/app/Services/LocalPresenceService.php');
$carry = readFileOrFail($root . '/backend/app/Services/CarryingRiskService.php');
$activity = readFileOrFail($root . '/backend/app/Services/LocalActivityService.php');
$exploration = readFileOrFail($root . '/backend/app/Services/HotspotExplorationService.php');
$quick = readFileOrFail($root . '/backend/app/Services/QuickCrimeService.php');
$dirty = readFileOrFail($root . '/backend/app/Services/DirtyJobService.php');
$controller = readFileOrFail($root . '/backend/app/Controllers/WorldMapController.php');
$routes = readFileOrFail($root . '/backend/routes/api.php');
$worldMap = readFileOrFail($root . '/backend/app/Services/WorldMapService.php');
$types = readFileOrFail($root . '/frontend/src/types/worldMap.ts');
$api = readFileOrFail($root . '/frontend/src/api/worldMap.ts');
$travelPanel = readFileOrFail($root . '/frontend/src/components/map/TravelPanel.tsx');
$activityPanel = readFileOrFail($root . '/frontend/src/components/map/LocalActivityPanel.tsx');
$locationPage = readFileOrFail($root . '/frontend/src/pages/LocationMapPage.tsx');
$dashboard = readFileOrFail($root . '/frontend/src/pages/DashboardPage.tsx');
$crimes = readFileOrFail($root . '/frontend/src/pages/CrimesPage.tsx');
$dirtyPage = readFileOrFail($root . '/frontend/src/pages/DirtyJobsPage.tsx');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
$apiDocs = readFileOrFail($root . '/backend/docs-api.md');

$runner->test('Migration tracks travel state and history tables', function () use ($runner, $migration): void {
    foreach (['user_travel_logs', 'user_location_presence', 'travel_event_templates', 'travel_route_type', 'travel_status', 'last_local_action_at'] as $needle) {
        $runner->assertContains($needle, $migration);
    }
});

$runner->test('Migration extends location and action rule tables', function () use ($runner, $migration): void {
    foreach (['travel_risk_level', 'travel_event_profile', 'local_presence_required_default', 'exploration_energy_cost', 'local_presence_required_message', 'travel_required_before_accept'] as $needle) {
        $runner->assertContains($needle, $migration);
    }
});

$runner->test('Seed adds travel event templates and local travel hints', function () use ($runner, $seed): void {
    foreach (['overheard-local-rumor', 'police-checkpoint-warning', 'rival-gang-presence', 'found-dirty-job-lead', 'requires_current_location = 1', 'Travel here to use'] as $needle) {
        $runner->assertContains($needle, $seed);
    }
});

$runner->test('Seed makes important hotspots meaningful', function () use ($runner, $seed): void {
    foreach (['container-yard', 'workers-bar', 'parking-lots', 'scrapyard', 'basement-bars', 'police-district', 'warehouses'] as $needle) {
        $runner->assertContains($needle, $seed);
    }
});

$runner->test('Travel service changes location, spends resources, and records logs', function () use ($runner, $travel): void {
    foreach (['UPDATE users', 'cash = cash - ?', 'energy = energy - ?', 'UPDATE user_location_state', 'recordTravelLog', 'user_travel_logs'] as $needle) {
        $runner->assertContains($needle, $travel);
    }
});

$runner->test('Travel response includes meaningful gameplay payload', function () use ($runner, $travel): void {
    foreach (['unlockedActions', 'localActivitySummary', 'heatChange', 'discoveredOpportunity', 'historyEntry', 'possibleActions', 'updatedPlayerStats'] as $needle) {
        $runner->assertContains($needle, $travel);
    }
});

$runner->test('Travel service supports same-location no-op and travel-and-explore', function () use ($runner, $travel): void {
    foreach (['same_location', 'You are already at', 'travelAndExplore', 'HotspotExplorationService', 'Travel did not finish'] as $needle) {
        $runner->assertContains($needle, $travel);
    }
});

$runner->test('Travel risk has route types, costs, warnings, and event chances', function () use ($runner, $travelRisk): void {
    foreach (['ROUTE_TYPES', 'cheap', 'fast', 'low_profile', 'back_roads', 'event_chance', 'police_stop_chance', 'rival_event_chance', 'travel_risk_score', 'warnings'] as $needle) {
        $runner->assertContains($needle, $travelRisk);
    }
});

$runner->test('Travel events can create opportunities and heat changes', function () use ($runner, $travelEvent): void {
    foreach (['maybeCreate', 'travel_event_templates', 'createOpportunity', 'local_opportunities', 'heat_delta', 'police_checkpoint', 'rival_presence'] as $needle) {
        $runner->assertContains($needle, $travelEvent);
    }
});

$runner->test('Carrying risk considers drugs and equipped weapons safely', function () use ($runner, $carry): void {
    foreach (['user_drugs', 'user_weapons', 'risk_bonus', 'illegal_drug_units', 'equipped_weapons'] as $needle) {
        $runner->assertContains($needle, $carry);
    }
});

$runner->test('Local presence service exposes current, assert, arrival, and exploration methods', function () use ($runner, $presence): void {
    foreach (['current(', 'isAt(', 'assertAt(', 'updatePresenceAfterArrival', 'markExplored', 'user_location_presence'] as $needle) {
        $runner->assertContains($needle, $presence);
    }
});

$runner->test('Hotspot exploration now requires physical presence', function () use ($runner, $exploration): void {
    foreach (['Travel to', 'before exploring this hotspot', 'LocalPresenceService', 'last_local_action_at'] as $needle) {
        $runner->assertContains($needle, $exploration);
    }
});

$runner->test('Local activity response distinguishes presence and travel purpose', function () use ($runner, $activity): void {
    foreach (['travelPurpose', 'remoteActions', 'localUnlocks', 'localActivitySummary', 'availabilityLabel', 'Requires local presence'] as $needle) {
        $runner->assertContains($needle, $activity);
    }
});

$runner->test('World map service exposes route options and richer current location state', function () use ($runner, $worldMap): void {
    foreach (['route_options', 'travel_route_type', 'travel_status', 'arrived_at', 'last_local_action_at', 'TravelRiskService'] as $needle) {
        $runner->assertContains($needle, $worldMap);
    }
});

$runner->test('World map controller and routes expose v0.6.3 endpoints', function () use ($runner, $controller, $routes): void {
    foreach (['travelAndExplore', 'travelHistory', '/api/world-map/travel-and-explore', '/api/world-map/travel-history'] as $needle) {
        $runner->assertContains($needle, $controller . $routes);
    }
});

$runner->test('Quick crimes enforce local presence and give travel messages', function () use ($runner, $quick): void {
    foreach (['locationRuleForTemplate', 'requires_current_location', 'local_presence', 'travel_hint', 'Travel to'] as $needle) {
        $runner->assertContains($needle, $quick);
    }
});

$runner->test('Dirty jobs enforce local presence on accept and execution', function () use ($runner, $dirty): void {
    foreach (['validateLocalPresenceForOpportunity', 'validateLocalPresenceForRun', 'requires_presence_at_source', 'travel_required_before_execute', 'Travel to'] as $needle) {
        $runner->assertContains($needle, $dirty);
    }
});

$runner->test('Frontend world-map types include rich travel and local activity data', function () use ($runner, $types): void {
    foreach (['TravelRouteOption', 'TravelEventNotice', 'TravelAndExploreResponse', 'travelPurpose', 'localPresenceSatisfied', 'unlockedActions'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('Frontend API client exposes travel-and-explore and travel history', function () use ($runner, $api): void {
    foreach (['travelAndExplore', '/world-map/travel-and-explore', 'getTravelHistory', '/world-map/travel-history'] as $needle) {
        $runner->assertContains($needle, $api);
    }
});

$runner->test('Travel panel explains travel purpose, risks, routes, and results', function () use ($runner, $travelPanel): void {
    foreach (['Travel here to unlock', 'Can view remotely', 'Event chance', 'Travel & Explore', 'travel-result-panel', 'route_options'] as $needle) {
        $runner->assertContains($needle, $travelPanel);
    }
});

$runner->test('Local activity panel distinguishes available here and travel required', function () use ($runner, $activityPanel): void {
    foreach (['Travel here to unlock', 'Available here', 'Requires local presence', 'Travel here to explore', 'travelPurpose'] as $needle) {
        $runner->assertContains($needle, $activityPanel);
    }
});

$runner->test('Location map page wires travel result and Travel & Explore', function () use ($runner, $locationPage): void {
    foreach (['travelAndExploreSelectedHotspot', 'travelResult', 'TravelPanel', 'onTravelAndExplore', 'setTravelResult'] as $needle) {
        $runner->assertContains($needle, $locationPage);
    }
});

$runner->test('Dashboard shows current location widget', function () use ($runner, $dashboard): void {
    foreach (['getCurrentLocation', 'Current Location', 'Open Map', 'Travel affects which quick crimes'] as $needle) {
        $runner->assertContains($needle, $dashboard);
    }
});

$runner->test('Crimes page shows local presence requirements', function () use ($runner, $crimes): void {
    foreach (['Requires local presence', 'Travel Here required', 'local_presence', 'travel_hint'] as $needle) {
        $runner->assertContains($needle, $crimes);
    }
});

$runner->test('Dirty Jobs page shows local presence requirements', function () use ($runner, $dirtyPage): void {
    foreach (['Requires local presence', 'Travel Here required', 'presence_status', 'travel_hint'] as $needle) {
        $runner->assertContains($needle, $dirtyPage);
    }
});

$runner->test('Documentation records v0.6.3 travel update', function () use ($runner, $docs, $apiDocs): void {
    foreach (['v0.6.3 — Meaningful Travel & Local Presence', 'Travel now unlocks local actions', 'Travel & Explore', 'travel-history'] as $needle) {
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
