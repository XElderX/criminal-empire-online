<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$migration = readFileOrFail(
    $root . '/backend/database/migrations/004_crew_portraits_design.sql'
);
$manifest = readFileOrFail(
    $root . '/backend/app/Config/CrewPortraitManifest.php'
);
$assignment = readFileOrFail(
    $root . '/backend/app/Services/PortraitAssignmentService.php'
);
$crewService = readFileOrFail(
    $root . '/backend/app/Services/CrewService.php'
);
$recruitmentService = readFileOrFail(
    $root . '/backend/app/Services/RecruitmentService.php'
);
$agingService = readFileOrFail(
    $root . '/backend/app/Services/CrewAgingService.php'
);
$portraitCommand = readFileOrFail(
    $root . '/backend/commands/crew-portraits.php'
);
$worldCommand = readFileOrFail(
    $root . '/backend/commands/world.php'
);
$crewPage = readFileOrFail(
    $root . '/frontend/src/pages/CrewPage.tsx'
);
$recruitmentPage = readFileOrFail(
    $root . '/frontend/src/pages/RecruitmentPage.tsx'
);
$crewPortrait = readFileOrFail(
    $root . '/frontend/src/features/crew/components/CrewPortrait.tsx'
);

$runner->test('Portrait migration adds stable NPC identity fields', function () use (
    $runner,
    $migration
): void {
    foreach ([
        'portrait_set_key',
        'portrait_stage_cache',
        'portrait_focal_x',
        'portrait_focal_y',
        'birth_game_year',
        'birth_game_day',
        'last_age_processed_game_year',
    ] as $field) {
        $runner->assertContains($field, $migration);
    }
});

$runner->test('Portrait migration does not reset crew gameplay data', function () use (
    $runner,
    $migration
): void {
    foreach ([
        'strength =',
        'loyalty =',
        'morale =',
        'salary_weekly =',
        'personal_cash =',
    ] as $unsafeReset) {
        $runner->assertFalse(
            str_contains($migration, $unsafeReset),
            "Migration must not reset existing crew data: {$unsafeReset}"
        );
    }
});

$runner->test('Manifest declares fifty portrait sets', function () use (
    $runner,
    $manifest
): void {
    $runner->assertSame(
        50,
        substr_count($manifest, "'key' => 'portrait-set-")
    );
});

$runner->test('Portrait assignment enforces matching gender sets', function () use (
    $runner,
    $assignment
): void {
    $runner->assertContains('$manifest->enabledSets($gender)', $assignment);
    $runner->assertContains(
        'No enabled portrait sets match this NPC gender.',
        $assignment
    );
    $runner->assertContains("'male' => []", $assignment);
    $runner->assertContains("'female' => []", $assignment);
});

$runner->test('Portrait assignment never overwrites an existing identity', function () use (
    $runner,
    $assignment
): void {
    $runner->assertContains("if (!empty(\$npc['portrait_set_key']))", $assignment);
    $runner->assertContains('AND portrait_set_key IS NULL', $assignment);
});

$runner->test('Existing crew portrait backfill command is available', function () use (
    $runner,
    $portraitCommand
): void {
    $runner->assertContains("'backfill'", $portraitCommand);
    $runner->assertContains('backfillAll()', $portraitCommand);
    $runner->assertContains("'validate'", $portraitCommand);
    $runner->assertContains("'sync-stages'", $portraitCommand);
});

$runner->test('Crew and recruitment APIs use centralized presentation data', function () use (
    $runner,
    $crewService,
    $recruitmentService
): void {
    $runner->assertContains('CrewPresentationService', $crewService);
    $runner->assertContains('PortraitAssignmentService', $crewService);
    $runner->assertContains('CrewPresentationService', $recruitmentService);
    $runner->assertContains('PortraitAssignmentService', $recruitmentService);
});

$runner->test('Recruitment validates recruitable age on the backend', function () use (
    $runner,
    $recruitmentService
): void {
    $runner->assertContains('CrewAgeStageResolver', $recruitmentService);
    $runner->assertContains("['recruitable']", $recruitmentService);
    $runner->assertContains(
        'Candidate is outside the recruitable age range.',
        $recruitmentService
    );
});

$runner->test('Aging preserves NPC records and only updates age metadata', function () use (
    $runner,
    $agingService
): void {
    $runner->assertContains('UPDATE npcs', $agingService);
    $runner->assertContains('portrait_stage_cache = ?', $agingService);
    $runner->assertFalse(
        str_contains($agingService, 'DELETE FROM npcs'),
        'Aging must never replace or delete an NPC.'
    );
});

$runner->test('Year processing is exposed through the world command', function () use (
    $runner,
    $worldCommand
): void {
    $runner->assertContains("'process-year'", $worldCommand);
    $runner->assertContains('CrewAgingService', $worldCommand);
});

$requiredComponents = [
    'CrewCard.tsx',
    'CrewConditionMeters.tsx',
    'CrewEquipmentGrid.tsx',
    'CrewExperienceBar.tsx',
    'CrewHistoryTimeline.tsx',
    'CrewPortrait.tsx',
    'CrewProfile.tsx',
    'CrewRoleBadge.tsx',
    'CrewSkillGrid.tsx',
    'CrewStatusBadge.tsx',
    'CrewTraitList.tsx',
    'RecruitmentCard.tsx',
];

foreach ($requiredComponents as $component) {
    $runner->test("Crew component exists: {$component}", function () use (
        $runner,
        $root,
        $component
    ): void {
        $path = $root
            . '/frontend/src/features/crew/components/'
            . $component;

        $runner->assertTrue(is_file($path), "Missing component: {$path}");
    });
}

$runner->test('Crew portrait lazy-loads and has a safe fallback', function () use (
    $runner,
    $crewPortrait
): void {
    $runner->assertContains('loading="lazy"', $crewPortrait);
    $runner->assertContains('fallback_url', $crewPortrait);
    $runner->assertContains('onError', $crewPortrait);
    $runner->assertContains('alt=', $crewPortrait);
});

$runner->test('Crew overview includes filtering, sorting and view modes', function () use (
    $runner,
    $crewPage
): void {
    foreach ([
        'statusFilter',
        'roleFilter',
        'ageFilter',
        'sortKey',
        'viewMode',
    ] as $feature) {
        $runner->assertContains($feature, $crewPage);
    }
});

$runner->test('Recruitment cards show portrait-aware candidate information', function () use (
    $runner,
    $recruitmentPage
): void {
    $runner->assertContains('RecruitmentCard', $recruitmentPage);
    $runner->assertContains('ageFilter', $recruitmentPage);
    $runner->assertContains('onHire={hire}', $recruitmentPage);
});

$runner->test('All fifty adult portrait assets and thumbnails exist', function () use (
    $runner,
    $root
): void {
    for ($number = 1; $number <= 50; $number++) {
        $key = sprintf('portrait-set-%03d', $number);
        $basePath = $root
            . '/frontend/public/assets/crew/portraits/'
            . $key;

        $runner->assertTrue(is_file($basePath . '/adult.webp'));
        $runner->assertTrue(is_file($basePath . '/thumbs/adult.webp'));
    }
});

$runner->test('Fallback portrait exists', function () use (
    $runner,
    $root
): void {
    $runner->assertTrue(
        is_file(
            $root
                . '/frontend/public/assets/crew/portraits/fallback.svg'
        )
    );
});

exit($runner->finish());

function readFileOrFail(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Could not read test fixture: {$path}");
    }

    return $contents;
}
