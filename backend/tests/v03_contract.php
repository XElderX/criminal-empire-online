<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$schemaV1 = readFileOrFail($root . '/backend/database/migrations/001_schema.sql');
$schemaV2 = readFileOrFail(
    $root . '/backend/database/migrations/002_single_player_foundation.sql'
);
$schemaV3 = readFileOrFail(
    $root . '/backend/database/migrations/003_dirty_jobs_expansion.sql'
);
$seedV3 = readFileOrFail(
    $root . '/backend/database/seeders/003_dirty_jobs_seed.sql'
);
$routes = readFileOrFail($root . '/backend/routes/api.php');
$auth = readFileOrFail($root . '/backend/app/Services/AuthService.php');
$tutorial = readFileOrFail($root . '/backend/app/Services/TutorialService.php');
$dirtyJobs = readFileOrFail($root . '/backend/app/Services/DirtyJobService.php');
$warehouse = readFileOrFail($root . '/backend/app/Services/WarehouseService.php');
$crew = readFileOrFail($root . '/backend/app/Services/CrewService.php');

$recruitment = readFileOrFail(
    $root . '/backend/app/Services/RecruitmentService.php'
);

$runner->test('Base schema uses the 500-dollar default', function () use (
    $runner,
    $schemaV1
): void {
    $runner->assertContains(
        'cash BIGINT UNSIGNED NOT NULL DEFAULT 500,',
        $schemaV1
    );
});

$runner->test('Upgrade migration preserves existing users while changing default', function () use (
    $runner,
    $schemaV2
): void {
    $runner->assertContains(
        'ALTER TABLE users MODIFY cash BIGINT UNSIGNED NOT NULL DEFAULT 500;',
        $schemaV2
    );
    $runner->assertFalse(
        str_contains($schemaV2, 'UPDATE users SET cash = 500'),
        'Existing balances must not be reset.'
    );
});

$requiredTables = [
    'user_tutorial_progress',
    'tutorial_step_logs',
    'item_definitions',
    'user_items',
    'crew_equipment',
    'crew_history',
    'npc_contacts',
    'contact_relationships',
    'dirty_job_templates',
    'dirty_job_opportunities',
    'dirty_job_runs',
    'dirty_job_preparations',
    'dirty_job_assignments',
    'dirty_job_equipment',
    'building_types',
    'property_listings',
    'player_buildings',
    'warehouse_storage',
    'vehicles',
    'building_upgrades',
    'player_building_upgrades',
    'storage_logs',
    'heat_actions',
];

foreach ($requiredTables as $table) {
    $runner->test("Migration creates {$table}", function () use (
        $runner,
        $schemaV3,
        $table
    ): void {
        $runner->assertContains("CREATE TABLE {$table}", $schemaV3);
    });
}

$runner->test('Existing players receive completed tutorial fallback state', function () use (
    $runner,
    $schemaV3
): void {
    $runner->assertContains("'completed',\n  'completed'", $schemaV3);
    $runner->assertContains('FROM users', $schemaV3);
});

$runner->test('Tutorial log events are uniquely protected', function () use (
    $runner,
    $schemaV3
): void {
    $runner->assertContains(
        'UNIQUE KEY unique_tutorial_event (user_id, step_code, event_type)',
        $schemaV3
    );
});

$runner->test('Dirty Job acceptance has request and opportunity idempotency', function () use (
    $runner,
    $schemaV3
): void {
    $runner->assertContains(
        'UNIQUE KEY unique_dirty_job_request (user_id, idempotency_key)',
        $schemaV3
    );
    $runner->assertContains(
        'UNIQUE KEY unique_dirty_job_opportunity (user_id, opportunity_id)',
        $schemaV3
    );
});

$runner->test('One crew member and one role are enforced per Dirty Job', function () use (
    $runner,
    $schemaV3
): void {
    $runner->assertContains(
        'UNIQUE KEY unique_dirty_job_member (dirty_job_run_id, gang_member_id)',
        $schemaV3
    );
    $runner->assertContains(
        'UNIQUE KEY unique_dirty_job_role (dirty_job_run_id, role_code)',
        $schemaV3
    );
});

$runner->test('Crew equipment slots and assets are uniquely protected', function () use (
    $runner,
    $schemaV3
): void {
    $runner->assertContains(
        'UNIQUE KEY unique_member_slot (gang_member_id, equipment_slot)',
        $schemaV3
    );
    $runner->assertContains(
        'UNIQUE KEY unique_member_asset (gang_member_id, asset_type, asset_id)',
        $schemaV3
    );
});

$runner->test('Warehouse assets and purchases are uniquely protected', function () use (
    $runner,
    $schemaV3
): void {
    $runner->assertContains(
        'UNIQUE KEY unique_warehouse_asset (warehouse_id, asset_type, asset_id)',
        $schemaV3
    );
    $runner->assertContains(
        'UNIQUE KEY unique_user_listing_purchase (user_id, source_listing_id)',
        $schemaV3
    );
});

$runner->test('New registrations use centralized starting state', function () use (
    $runner,
    $auth
): void {
    $runner->assertContains('GameConfig::STARTING_CASH', $auth);
    $runner->assertContains('GameConfig::STARTING_BANK_CASH', $auth);
    $runner->assertContains('GameConfig::STARTING_DIRTY_MONEY', $auth);
    $runner->assertContains('createForNewUser($userId)', $auth);
});

$runner->test('Tutorial progress is validated from gameplay state', function () use (
    $runner,
    $tutorial
): void {
    foreach ([
        'hasCompletedLegalJob',
        'hasAttemptedIllegalWork',
        'hasCrewMember',
        'hasEquippedCrewMember',
        'hasPreparedDirtyJob',
        'hasResolvedDirtyJob',
    ] as $method) {
        $runner->assertContains($method, $tutorial);
    }
});

$runner->test('Tutorial reward is guarded by claimed reward state', function () use (
    $runner,
    $tutorial
): void {
    $runner->assertContains('tutorial_completion_cash', $tutorial);
    $runner->assertContains('in_array($rewardCode, $rewardsClaimed, true)', $tutorial);
});

$runner->test('Dirty Job execution locks users and crew availability', function () use (
    $runner,
    $dirtyJobs
): void {
    $runner->assertContains('FOR UPDATE', $dirtyJobs);
    $runner->assertContains("status = 'busy'", $dirtyJobs);
    $runner->assertContains('current_assignment_id = ?', $dirtyJobs);
});

$runner->test('Dirty Job resolution is one-way and reward-safe', function () use (
    $runner,
    $dirtyJobs
): void {
    $runner->assertContains("if (\$run['status'] !== 'executing')", $dirtyJobs);
    $runner->assertContains('resolved_at = NOW()', $dirtyJobs);
    $runner->assertContains('This Dirty Job has already been resolved or is not executing.', $dirtyJobs);
});

$runner->test('Warehouse transfers validate positive quantities and ownership', function () use (
    $runner,
    $warehouse
): void {
    $runner->assertContains('if ($quantity < 1)', $warehouse);
    $runner->assertContains('building.user_id = ?', $warehouse);
    $runner->assertContains('FOR UPDATE', $warehouse);
});

$runner->test('Warehouse storage checks capacity before deposit', function () use (
    $runner,
    $warehouse
): void {
    $runner->assertContains('Warehouse storage capacity would be exceeded.', $warehouse);
});

$runner->test('Crew dismissal preserves member row and records history', function () use (
    $runner,
    $crew
): void {
    $runner->assertContains("status = 'dismissed'", $crew);
    $runner->assertContains("'Dismissed from the crew'", $crew);
    $runner->assertFalse(
        str_contains($crew, 'DELETE FROM player_gang_members'),
        'Dismissal must not delete the persistent character.'
    );
});

$runner->test('Dirty Job service uses centralized experience updates for crew progression', function () use (
    $runner,
    $dirtyJobs
): void {
    $runner->assertContains('grantCrew(', $dirtyJobs);
    $runner->assertContains('grantPlayer(', $dirtyJobs);
});

$runner->test('Crew dismissal blocks active assignments', function () use (
    $runner,
    $crew
): void {
    $runner->assertContains(
        'Cancel or resolve the active assignment before dismissal.',
        $crew
    );
});

$runner->test('Dismissed crew can be reactivated without duplicate NPC rows', function () use (
    $runner,
    $recruitment
): void {
    $runner->assertContains('reactivateDismissedMember', $recruitment);
    $runner->assertContains("status = 'active'", $recruitment);
    $runner->assertContains('Rejoined the crew', $recruitment);
});


$requiredRoutes = [
    '/api/tutorial',
    '/api/tutorial/advance',
    '/api/tutorial/skip',
    '/api/tutorial/reopen',
    '/api/dirty-jobs',
    '/api/dirty-jobs/{id}/accept',
    '/api/dirty-job-runs/{id}/prepare',
    '/api/dirty-job-runs/{id}/assign-crew',
    '/api/dirty-job-runs/{id}/execute',
    '/api/dirty-job-runs/{id}/decision',
    '/api/dirty-job-runs/{id}/resolve',
    '/api/my-gang/{id}/equip',
    '/api/my-gang/{id}/dismiss',
    '/api/warehouses',
    '/api/warehouse-listings/{id}/purchase',
    '/api/warehouses/{id}/transfer',
    '/api/heat/lay-low',
];

foreach ($requiredRoutes as $route) {
    $runner->test("API route exists: {$route}", function () use (
        $runner,
        $routes,
        $route
    ): void {
        $runner->assertContains($route, $routes);
    });
}

$seedRequirements = [
    "'steal_car_lamps'",
    "'shoplift_electronics'",
    "'apartment_burglary'",
    "'garage_parts_raid'",
    "'steal_delivery_van'",
    "'collect_rival_payment'",
    "'warehouse_grow_cycle'",
    "'crowbar'",
    "'lockpick_set'",
    "'work_gloves'",
    "'duffel_bag'",
    "'first_aid_kit'",
    "'Old Freight Annex'",
    "'Riverside Lockup'",
    "'stronger_locks'",
    "'basic_alarm'",
    "'hidden_compartment'",
];

foreach ($seedRequirements as $value) {
    $runner->test("Seed content includes {$value}", function () use (
        $runner,
        $seedV3,
        $value
    ): void {
        $runner->assertContains($value, $seedV3);
    });
}

$runner->test('Entry warehouse price is balanced above starting cash', function () use (
    $runner,
    $seedV3
): void {
    $runner->assertContains("'Old Freight Annex'", $seedV3);
    $runner->assertContains("  7500,", $seedV3);
});

$runner->test('Production operation is explicitly abstract gameplay', function () use (
    $runner,
    $seedV3
): void {
    $runner->assertContains('abstract', strtolower($seedV3));
    $runner->assertContains('fictional', strtolower($seedV3));
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
