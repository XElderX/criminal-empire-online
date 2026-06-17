<?php

require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Services\WarehouseService;

App::boot(dirname(__DIR__));

$command = $argv[1] ?? 'status';

if ($command === 'process-costs') {
    $result = (new WarehouseService())->processWeeklyCosts();
} elseif ($command === 'status') {
    $pdo = Database::pdo();
    $result = [
        'owned_warehouses' => (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM player_buildings building
                JOIN building_types type
                    ON type.id = building.building_type_id
                WHERE type.code = 'warehouse'
                  AND building.status <> 'closed'
            SQL
        )->fetchColumn(),
        'stored_asset_rows' => (int) $pdo->query(
            'SELECT COUNT(*) FROM warehouse_storage WHERE quantity > 0'
        )->fetchColumn(),
        'stored_vehicles' => (int) $pdo->query(
            "SELECT COUNT(*) FROM vehicles WHERE status = 'stored'"
        )->fetchColumn(),
        'operating_debt' => (int) $pdo->query(
            'SELECT COALESCE(SUM(operating_debt), 0) FROM player_buildings'
        )->fetchColumn(),
    ];
} else {
    fwrite(
        STDERR,
        "Usage: php commands/warehouse.php status|process-costs\n"
    );
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo PHP_EOL;
