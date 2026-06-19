<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail($root . '/backend/database/migrations/010_v051_boss_character_integration.sql');
$boss = readFileOrFail($root . '/backend/app/Services/BossCharacterService.php');
$auth = readFileOrFail($root . '/backend/app/Services/AuthService.php');
$bossController = readFileOrFail($root . '/backend/app/Controllers/BossController.php');
$adminController = readFileOrFail($root . '/backend/app/Controllers/AdminController.php');
$crew = readFileOrFail($root . '/backend/app/Services/CrewService.php');
$crime = readFileOrFail($root . '/backend/app/Services/CrimeOpportunityService.php');
$quick = readFileOrFail($root . '/backend/app/Services/QuickCrimeService.php');
$dirtyJobs = readFileOrFail($root . '/backend/app/Services/DirtyJobService.php');
$routes = readFileOrFail($root . '/backend/routes/api.php');
$types = readFileOrFail($root . '/frontend/src/types.ts');
$authPage = readFileOrFail($root . '/frontend/src/pages/AuthPage.tsx');
$crewPage = readFileOrFail($root . '/frontend/src/pages/CrewPage.tsx');
$crimesPage = readFileOrFail($root . '/frontend/src/pages/CrimesPage.tsx');
$dirtyJobsPage = readFileOrFail($root . '/frontend/src/pages/DirtyJobsPage.tsx');
$heatPage = readFileOrFail($root . '/frontend/src/pages/HeatPolicePage.tsx');
$adminPage = readFileOrFail($root . '/frontend/src/pages/AdminPage.tsx');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');

$runner->test('v0.5.1 migration adds boss operational skill fields', function () use ($runner, $migration): void {
    foreach (['boss_shooting', 'boss_driving', 'boss_stealth', 'boss_intimidation', 'boss_discipline', 'boss_street_knowledge', 'boss_endurance'] as $field) {
        $runner->assertContains($field, $migration);
    }
});

$runner->test('v0.5.1 migration allows boss actors in crime assignment tables', function () use ($runner, $migration): void {
    foreach (['actor_type', 'actor_id', 'crime_opportunity_assignments', 'crime_run_assignments', 'quick_crime_run_crew'] as $needle) {
        $runner->assertContains($needle, $migration);
    }
});

$runner->test('Boss service exposes crew-like boss member and skills', function () use ($runner, $boss): void {
    foreach (['function asCrewMember', 'function renameInitialBoss', 'can_rename_initial_name', 'skills', 'driving', 'stealth', 'shooting', 'street_knowledge'] as $needle) {
        $runner->assertContains($needle, $boss);
    }
});

$runner->test('Registration and boss routes require explicit boss naming', function () use ($runner, $auth, $bossController, $routes): void {
    foreach (['boss_first_name', 'boss_last_name', 'boss_display_name'] as $needle) {
        $runner->assertContains($needle, $auth);
    }

    $runner->assertContains('function rename', $bossController);
    $runner->assertContains('/api/boss/rename', $routes);
});

$runner->test('Admin panel can clear a user heat profile to zero', function () use ($runner, $adminController, $adminPage, $routes): void {
    foreach (['function clearHeat', 'boss_personal_heat = 0', 'gang_heat = 0', 'admin.heat_cleared'] as $needle) {
        $runner->assertContains($needle, $adminController);
    }

    $runner->assertContains('/api/admin/users/{id}/heat/clear', $routes);
    $runner->assertContains('Set heat to 0', $adminPage);
    $runner->assertContains('Boss heat:', $adminPage);
});

$runner->test('Crew service includes boss in crew section and profile route', function () use ($runner, $crew): void {
    $runner->assertContains('array_unshift($members', $crew);
    $runner->assertContains('memberId === 0', $crew);
    $runner->assertContains('BossCharacterService', $crew);
});

$runner->test('Major crime service accepts boss actors', function () use ($runner, $crime): void {
    foreach (['bossActor', 'actor_type', 'bossInvolved', 'gang_member_id'] as $needle) {
        $runner->assertContains($needle, $crime);
    }
});

$runner->test('Quick crime service accepts boss actors', function () use ($runner, $quick): void {
    foreach (['bossActor', 'in_array(0, $crewIds', 'actor_type', 'boss_consequence'] as $needle) {
        $runner->assertContains($needle, $quick);
    }
});

$runner->test('Dirty jobs now require at least one assigned crew member', function () use ($runner, $dirtyJobs, $dirtyJobsPage): void {
    foreach (['requiredCrewMinimum', 'At least one crew member must be assigned to every Dirty Job.', 'return max(1, $configuredMinimum);'] as $needle) {
        $runner->assertContains($needle, $dirtyJobs);
    }

    foreach (['Every Dirty Job now requires at least', 'disabled={loading || selectedCrewCount < minimumCrew}', 'Assign at least {minimumCrew} crew member'] as $needle) {
        $runner->assertContains($needle, $dirtyJobsPage);
    }
});

$runner->test('Frontend types include boss and actor fields', function () use ($runner, $types): void {
    foreach (['is_boss', 'actor_type', 'skills', 'street_knowledge', 'can_rename_initial_name'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('Auth, crew, crime and heat pages expose boss naming and boss stats', function () use ($runner, $authPage, $crewPage, $crimesPage, $heatPage): void {
    $runner->assertContains('Boss first name', $authPage);
    $runner->assertContains('Boss surname', $authPage);
    $runner->assertContains('crewOnlyMembers', $crewPage);
    $runner->assertContains('Boss character', $heatPage);
    $runner->assertContains('Set boss name', $heatPage);
    $runner->assertContains('Boss skills now count like crew skills', $crimesPage);
    $runner->assertContains('selectedQuickCrewIds', $crimesPage);
});

$runner->test('Development log documents v0.5.1.3', function () use ($runner, $docs): void {
    $runner->assertContains('v0.5.1.3 — Dirty Job Crew Requirement Hotfix', $docs);
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
