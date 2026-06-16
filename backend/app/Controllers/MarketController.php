<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;

final class MarketController
{
    public function drugs(array $params = [], array $context = []): void
    {
        Response::json(['data' => Database::pdo()->query('SELECT d.*, dp.region, dp.price, dp.supply, dp.demand FROM drugs d JOIN drug_prices dp ON dp.drug_id=d.id ORDER BY dp.region,d.name')->fetchAll()]);
    }
}
