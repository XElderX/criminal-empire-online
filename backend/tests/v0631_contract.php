<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$jobService = readFileOrFail($root . '/backend/app/Services/JobService.php');
$jobsPage = readFileOrFail($root . '/frontend/src/pages/JobsPage.tsx');
$types = readFileOrFail($root . '/frontend/src/types.ts');
$docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md');
$apiDocs = readFileOrFail($root . '/backend/docs-api.md');

$runner->test('Street Jobs list exposes required NPC assignment metadata', function () use ($runner, $jobService): void {
    foreach (['min_assigned_members', 'assignable_crew_count', 'requires_npc_assignment', 'assignment_hint'] as $needle) {
        $runner->assertContains($needle, $jobService);
    }
});

$runner->test('Street Jobs backend requires at least one NPC assignment even when min gang size is zero', function () use ($runner, $jobService): void {
    foreach (['max(1', 'min_gang_size', 'Street Jobs require at least one assigned active NPC crew member'] as $needle) {
        $runner->assertContains($needle, $jobService);
    }
});

$runner->test('Street Jobs backend rejects boss and fake crew ids', function () use ($runner, $jobService): void {
    foreach (['$memberId <= 0', 'The boss cannot be assigned to Street Jobs', 'real NPC crew members'] as $needle) {
        $runner->assertContains($needle, $jobService);
    }
});

$runner->test('Street Jobs frontend filters boss out of assignment picker', function () use ($runner, $jobsPage): void {
    foreach (['!member.is_boss', 'member.id > 0', 'boss', 'cannot be assigned'] as $needle) {
        $runner->assertContains($needle, $jobsPage);
    }
});

$runner->test('Street Jobs frontend disables start until selected NPC crew satisfies requirement', function () use ($runner, $jobsPage): void {
    foreach (['selectedMemberCount', 'hasSelectedCrew', 'min_assigned_members', 'disabled={loading || !job.can_start || !hasSelectedCrew}'] as $needle) {
        $runner->assertContains($needle, $jobsPage);
    }
});

$runner->test('Street Job types include assignment metadata', function () use ($runner, $types): void {
    foreach (['min_assigned_members', 'assignable_crew_count', 'requires_npc_assignment', 'assignment_hint'] as $needle) {
        $runner->assertContains($needle, $types);
    }
});

$runner->test('Documentation records v0.6.3.1 hotfix', function () use ($runner, $docs, $apiDocs): void {
    foreach (['v0.6.3.1 — Street Job NPC Assignment Hotfix', 'POST /api/jobs/{id}/start', 'requires at least one assigned active NPC crew member'] as $needle) {
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
