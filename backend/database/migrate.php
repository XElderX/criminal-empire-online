<?php
require_once __DIR__ . '/../app/Core/Autoload.php';
use App\Core\App; use App\Core\Database;
App::boot(dirname(__DIR__));
$pdo = Database::pdo();
foreach (glob(__DIR__ . '/migrations/*.sql') as $file) { $pdo->exec(file_get_contents($file)); echo "Migrated: $file\n"; }
foreach (glob(__DIR__ . '/seeders/*.sql') as $file) { $pdo->exec(file_get_contents($file)); echo "Seeded: $file\n"; }
echo "Done.\n";
