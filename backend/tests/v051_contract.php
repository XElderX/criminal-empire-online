<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail($root . '/backend/database/migrations/010_v051_boss_character_integration.sql');
$boss = readFileOrFail($root . '/backend/app/Services/BossCharacterService.php');
$crew = readFileOrFail($root . '/backend/app/Services/CrewService.php');
$crime = readFileOrFail($root . '/backend/app/Services/CrimeOpportunityService.php');
$quick = readFileOrFail($root . '/backend/app/Services/QuickCrimeService.php');
$types = readFileOrFail($root . '/frontend/src/types.ts');
$crewPage = readFileOrFail($root . '/frontend/src/pages/CrewPage.tsx');
$crimesPage = readFileOrFail($root . '/frontend/src/pages/CrimesPage.tsx');
$heatPage = readFileOrFail($root . '/frontend/src/pages/HeatPolicePage.tsx');
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
    foreach (['function asCrewMember', 'skills', 'driving', 'stealth', 'shooting', 'street_knowledge'] as $needle) {
        $runner->assertContains($needle, $boss);
    }
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

$runner->test('Frontend types include boss and actor fields', function () use ($runner, $types): void {
    foreach (['is_boss', 'actor_type', 'skills', 'street_knowledge'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('Crew and crime pages expose boss selection and boss stats', function () use ($runner, $crewPage, $crimesPage, $heatPage): void {
    $runner->assertContains('crewOnlyMembers', $crewPage);
    $runner->assertContains('Boss character', $heatPage);
    $runner->assertContains('Boss skills now count like crew skills', $crimesPage);
    $runner->assertContains('selectedQuickCrewIds', $crimesPage);
});

$runner->test('Development log documents v0.5.1', function () use ($runner, $docs): void {
    $runner->assertContains('v0.5.1 — Boss Character Integration', $docs);
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
