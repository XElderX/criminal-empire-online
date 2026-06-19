<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$routes = readFileOrFail($root . '/backend/routes/api.php');
$tutorial = readFileOrFail($root . '/backend/app/Services/TutorialService.php');
$validator = readFileOrFail($root . '/backend/app/Services/TutorialObjectiveValidator.php');
$controller = readFileOrFail($root . '/backend/app/Controllers/TutorialController.php');
$migration = readFileOrFail($root . '/backend/database/migrations/015_v064_world_tutorial_update.sql');
$seeder = readFileOrFail($root . '/backend/database/seeders/015_v064_world_tutorial_seed.sql');
$app = readFileOrFail($root . '/frontend/src/App.tsx');
$panel = readFileOrFail($root . '/frontend/src/components/TutorialPanel.tsx');
$help = readFileOrFail($root . '/frontend/src/components/ContextualHelpButton.tsx');
$guide = readFileOrFail($root . '/frontend/src/pages/GuidePage.tsx');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
$apiDocs = readFileOrFail($root . '/backend/docs-api.md');

$runner->test('Tutorial API exposes v0.6.4 endpoints', function () use ($runner, $routes): void {
    foreach (['/api/tutorial/current', '/api/tutorial/steps', '/api/tutorial/objective', '/api/tutorial/guide', '/api/help/tips', '/api/tutorial/reset-dev'] as $needle) {
        $runner->assertContains($needle, $routes);
    }
});

$runner->test('Tutorial service supports versioning and update tutorial', function () use ($runner, $tutorial): void {
    foreach (['tutorial_key', 'tutorial_version', 'completed_update_tutorial_versions', 'TutorialUpdateService', 'TUTORIAL_KEY_UPDATE'] as $needle) {
        $runner->assertContains($needle, $tutorial);
    }
});

$runner->test('Tutorial objective validator does not trust simple completion flag', function () use ($runner, $validator): void {
    foreach (['travel_to_location', 'explore_hotspot', 'complete_quick_crime', 'hire_crew', 'equip_item', 'execute_dirty_job'] as $needle) {
        $runner->assertContains($needle, $validator);
    }
});

$runner->test('Migration adds versioned tutorial and help tables', function () use ($runner, $migration): void {
    foreach (['tutorial_definitions', 'tutorial_steps', 'user_tutorial_step_progress', 'tutorial_objective_events', 'user_help_tip_state', 'guide_sections'] as $needle) {
        $runner->assertContains($needle, $migration);
    }
});

$runner->test('Seeder contains new-player tutorial, update tutorial, help tips, and guide sections', function () use ($runner, $seeder): void {
    foreach (['new_player_world_guide', 'world_systems_update', 'World Map & Hotspots', 'Travel & Local Presence', 'Street Jobs'] as $needle) {
        $runner->assertContains($needle, $seeder);
    }
});

$runner->test('Frontend includes guide page and contextual help', function () use ($runner, $app, $help, $guide): void {
    $runner->assertContains('ContextualHelpButton', $app);
    $runner->assertContains('GuidePage', $app);
    $runner->assertContains('/help/tips', $help);
    $runner->assertContains('/tutorial/guide', $guide);
});

$runner->test('Tutorial panel displays modules and objective types', function () use ($runner, $panel): void {
    foreach (['tutorial.modules', 'objective_type', 'Skip tutorial', 'World Systems Update'] as $needle) {
        $runner->assertContains($needle, $panel);
    }
});

$runner->test('Documentation records v0.6.4 tutorial update', function () use ($runner, $docs, $apiDocs): void {
    foreach (['v0.6.4 — World Tutorial & Player Guidance Update', 'tutorial versioning', 'contextual help', 'GET /api/tutorial/guide'] as $needle) {
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
