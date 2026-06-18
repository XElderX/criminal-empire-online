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
            executeSqlStatements($pdo, $sql, $file);

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

function executeSqlStatements(\PDO $pdo, string $sql, string $file): void
{
    $statements = splitSqlStatements($sql);

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (\PDOException $exception) {
            if (isIgnorableSqlException($exception)) {
                fwrite(
                    STDERR,
                    "Skipped already-applied statement while processing {$file}.\n"
                );
                continue;
            }

            throw $exception;
        }
    }
}

/**
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;

    foreach (preg_split("/\r\n|\n|\r/", $sql) ?: [] as $line) {
        $trimmed = ltrim($line);

        if (
            $trimmed === ''
            || str_starts_with($trimmed, '-- ')
            || str_starts_with($trimmed, '--')
            || str_starts_with($trimmed, '#')
        ) {
            continue;
        }

        $buffer .= $line . "\n";

        $length = strlen($line);

        for ($index = 0; $index < $length; $index++) {
            $character = $line[$index];
            $previous = $index > 0 ? $line[$index - 1] : '';

            if ($character === "'" && $previous !== '\\' && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                continue;
            }

            if ($character === '"' && $previous !== '\\' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                continue;
            }

            if ($character === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $statement = trim($buffer);

                if ($statement !== '') {
                    $statements[] = rtrim($statement, ';');
                }

                $buffer = '';
            }
        }
    }

    $remainder = trim($buffer);

    if ($remainder !== '') {
        $statements[] = $remainder;
    }

    return $statements;
}

function isIgnorableSqlException(\PDOException $exception): bool
{
    $driverCode = (int) ($exception->errorInfo[1] ?? 0);

    return in_array($driverCode, [1050, 1060, 1061, 1062], true);
}
