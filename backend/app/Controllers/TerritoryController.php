<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;

final class TerritoryController
{
    public function index(array $params = [], array $context = []): void
    {
        $territories = Database::pdo()->query(
            <<<'SQL'
                SELECT
                    territory.*,
                    gang.name AS owner_gang
                FROM territories territory
                LEFT JOIN gangs gang ON gang.id = territory.owner_gang_id
                ORDER BY territory.id
            SQL
        )->fetchAll();

        Response::json(['data' => $territories]);
    }
}
