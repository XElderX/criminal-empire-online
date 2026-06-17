<?php

require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\App;
use App\Core\Database;
App::boot(dirname(__DIR__));

$pdo = Database::pdo();

createTrackingTables($pdo);
runSqlFiles($pdo, __DIR__ . '/migrations/*.sql', 'migration');
runSqlFiles($pdo, __DIR__ . '/seeders/*.sql', 'seeder');

echo "Done.\n";

function createTrackingTables(\PDO $pdo): void
{
    $pdo->exec(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL
    );

    $pdo->exec(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS schema_seeders (
                seeder VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL
    );
}

function runSqlFiles(
    \PDO $pdo,
    string $pattern,
    string $type
): void {
    $files = glob($pattern) ?: [];
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        $trackingTable = $type === 'migration'
            ? 'schema_migrations'
            : 'schema_seeders';
        $trackingColumn = $type === 'migration'
            ? 'migration'
            : 'seeder';

        if (hasAlreadyRun($pdo, $trackingTable, $trackingColumn, $name)) {
            $label = $type === 'migration' ? 'Skipped' : 'Skipped seed';
            echo "{$label}: {$file}\n";
            continue;
        }

        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new RuntimeException("Could not read SQL file: {$file}");
        }

        try {
            $pdo->exec($sql);

            $insert = $pdo->prepare(
                "INSERT INTO {$trackingTable} ({$trackingColumn}) VALUES (?)"
            );
            $insert->execute([$name]);
        } catch (\Throwable $exception) {
            fwrite(STDERR, "Failed while processing {$file}.\n");
            throw $exception;
        }

        $label = $type === 'migration' ? 'Migrated' : 'Seeded';
        echo "{$label}: {$file}\n";
    }
}

function hasAlreadyRun(
    \PDO $pdo,
    string $table,
    string $column,
    string $name
): bool {
    $statement = $pdo->prepare(
        "SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1"
    );
    $statement->execute([$name]);

    return (bool) $statement->fetchColumn();
}
