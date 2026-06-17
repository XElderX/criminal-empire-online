<?php

require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Services\DirtyJobGeneratorService;

App::boot(dirname(__DIR__));

$command = $argv[1] ?? 'status';
$generator = new DirtyJobGeneratorService();

$result = match ($command) {
    'status' => dirtyJobStatus(),
    'refresh' => $generator->refreshForAllUsers(),
    'expire' => [
        'opportunities_expired' => $generator->expireOldOpportunities(),
    ],
    default => null,
};

if ($result === null) {
    fwrite(
        STDERR,
        "Usage: php commands/dirty-jobs.php status|refresh|expire\n"
    );
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo PHP_EOL;

function dirtyJobStatus(): array
{
    $pdo = Database::pdo();

    return [
        'templates' => (int) $pdo->query(
            'SELECT COUNT(*) FROM dirty_job_templates WHERE active = 1'
        )->fetchColumn(),
        'available_opportunities' => (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_opportunities
                WHERE status = 'available'
                  AND expires_at > NOW()
            SQL
        )->fetchColumn(),
        'active_runs' => (int) $pdo->query(
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
        'resolved_runs' => (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_runs
                WHERE status IN ('completed', 'partially_completed', 'failed')
            SQL
        )->fetchColumn(),
    ];
}
