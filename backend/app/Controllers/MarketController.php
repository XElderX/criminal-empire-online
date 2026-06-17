<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;

final class MarketController
{
    public function drugs(array $params = [], array $context = []): void
    {
        $drugs = Database::pdo()->query(
            <<<'SQL'
                SELECT
                    drug.*,
                    price.region,
                    price.price,
                    price.supply,
                    price.demand
                FROM drugs drug
                JOIN drug_prices price ON price.drug_id = drug.id
                ORDER BY price.region, drug.name
            SQL
        )->fetchAll();

        Response::json(['data' => $drugs]);
    }
}
