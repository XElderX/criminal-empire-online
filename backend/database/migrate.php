<?php
require_once __DIR__ . '/../app/Core/Autoload.php';
use App\Core\App; use App\Core\Database;
App::boot(dirname(__DIR__));
$pdo = Database::pdo();

$pdo->exec("
  CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  )
");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS schema_seeders (
    seeder VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  )
");

foreach (glob(__DIR__ . '/migrations/*.sql') as $file) {
    $name = basename($file);
    $stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = ?');
    $stmt->execute([$name]);
    if ($stmt->fetchColumn()) {
        echo "Skipped: $file\n";
        continue;
    }

    $pdo->exec(file_get_contents($file));
    $insert = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $insert->execute([$name]);
    echo "Migrated: $file\n";
}

foreach (glob(__DIR__ . '/seeders/*.sql') as $file) {
    $name = basename($file);
    $stmt = $pdo->prepare('SELECT 1 FROM schema_seeders WHERE seeder = ?');
    $stmt->execute([$name]);
    if ($stmt->fetchColumn()) {
        echo "Skipped seed: $file\n";
        continue;
    }

    $pdo->exec(file_get_contents($file));
    $insert = $pdo->prepare('INSERT INTO schema_seeders (seeder) VALUES (?)');
    $insert->execute([$name]);
    echo "Seeded: $file\n";
}

echo "Done.\n";
