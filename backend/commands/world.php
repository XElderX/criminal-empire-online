<?php

require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Services\CrewRecoveryService;
use App\Services\DirtyJobGeneratorService;
use App\Services\EnergyService;
use App\Services\HeatService;
use App\Services\SalaryService;
use App\Services\WarehouseService;

App::boot(dirname(__DIR__));

$command = $argv[1] ?? 'status';

$result = match ($command) {
    'status' => worldStatus(),
    'process-hour' => processHour(),
    'process-day' => processDay(),
    'process-week' => processWeek(),
    default => null,
};

if ($result === null) {
    fwrite(
        STDERR,
        "Usage: php commands/world.php status|process-hour|process-day|process-week\n"
    );
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo PHP_EOL;

function worldStatus(): array
{
    $pdo = Database::pdo();

    return [
        'server_time' => date(DATE_ATOM),
        'active_starter_jobs' => (int) $pdo->query(
            "SELECT COUNT(*) FROM job_runs WHERE status = 'active'"
        )->fetchColumn(),
        'available_dirty_jobs' => (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_opportunities
                WHERE status = 'available'
                  AND available_from <= NOW()
                  AND expires_at > NOW()
            SQL
        )->fetchColumn(),
        'active_dirty_jobs' => (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_runs
                WHERE status IN (
                    'accepted',
                    'preparing',
                    'ready',
                    'executing',
                    'awaiting_decision'
                )
            SQL
        )->fetchColumn(),
        'available_recruits' => (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM recruitment_candidates
                WHERE status = 'available'
                  AND (expires_at IS NULL OR expires_at > NOW())
            SQL
        )->fetchColumn(),
        'active_crew_members' => (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM player_gang_members
                WHERE status <> 'dismissed'
            SQL
        )->fetchColumn(),
        'warehouses_owned' => (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM player_buildings building
                JOIN building_types type
                    ON type.id = building.building_type_id
                WHERE type.code = 'warehouse'
                  AND building.status <> 'closed'
            SQL
        )->fetchColumn(),
        'active_tutorials' => (int) $pdo->query(
            "SELECT COUNT(*) FROM user_tutorial_progress WHERE status = 'active'"
        )->fetchColumn(),
    ];
}

function processHour(): array
{
    $generator = new DirtyJobGeneratorService();

    return [
        'command' => 'process-hour',
        'processed_at' => date(DATE_ATOM),
        'energy' => (new EnergyService())->regenerate(10),
        'crew_recovery' => (new CrewRecoveryService())->process(),
        'dirty_jobs_expired' => $generator->expireOldOpportunities(),
    ];
}

function processDay(): array
{
    $generator = new DirtyJobGeneratorService();

    return [
        'command' => 'process-day',
        'processed_at' => date(DATE_ATOM),
        'energy' => (new EnergyService())->regenerate(30),
        'heat' => (new HeatService())->processDecay(),
        'crew_recovery' => (new CrewRecoveryService())->process(),
        'dirty_jobs_expired' => $generator->expireOldOpportunities(),
        'dirty_jobs_refreshed' => $generator->refreshForAllUsers(),
    ];
}

function processWeek(): array
{
    $generator = new DirtyJobGeneratorService();

    return [
        'command' => 'process-week',
        'processed_at' => date(DATE_ATOM),
        'energy' => (new EnergyService())->regenerate(100),
        'heat' => (new HeatService())->processDecay(),
        'crew_recovery' => (new CrewRecoveryService())->process(),
        'salaries' => (new SalaryService())->processDue(),
        'warehouse_costs' => (new WarehouseService())->processWeeklyCosts(),
        'dirty_jobs_expired' => $generator->expireOldOpportunities(),
        'dirty_jobs_refreshed' => $generator->refreshForAllUsers(),
    ];
}
