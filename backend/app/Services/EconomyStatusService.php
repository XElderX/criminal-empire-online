<?php

namespace App\Services;

use App\Core\Database;

final class EconomyStatusService
{
    public function report(): array
    {
        $pdo = Database::pdo();

        return [
            'player_money' => (int) $pdo->query(
                'SELECT COALESCE(SUM(cash + bank_cash + dirty_money), 0) FROM users'
            )->fetchColumn(),
            'npc_money' => (int) $pdo->query(
                'SELECT COALESCE(SUM(personal_cash + bank_cash), 0) FROM npcs'
            )->fetchColumn(),
            'player_business_hourly_income' => (int) $pdo->query(
                'SELECT COALESCE(SUM(hourly_income), 0) FROM businesses'
            )->fetchColumn(),
            'money_created' => $this->sumCategories([
                'starting_funds',
                'job_reward',
                'dirty_job_reward',
                'market_injection',
                'business_revenue',
            ]),
            'money_removed' => $this->sumCategories([
                'recruitment_fee',
                'equipment_purchase',
                'personal_expense',
                'warehouse_purchase',
                'warehouse_upgrade',
                'warehouse_operating_cost',
                'market_sink',
                'fine',
                'bribe',
            ]),
            'salary_payments' => $this->sumCategories(['salary_payment']),
            'starter_job_rewards' => $this->sumCategories(['job_reward']),
            'dirty_job_rewards' => $this->sumCategories(['dirty_job_reward']),
            'warehouse_operating_debt' => (int) $pdo->query(
                'SELECT COALESCE(SUM(operating_debt), 0) FROM player_buildings'
            )->fetchColumn(),
            'average_district_wealth' => (float) $pdo->query(
                'SELECT COALESCE(AVG(wealth), 0) FROM territories'
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
            'richest_npcs' => $pdo->query(
                <<<'SQL'
                    SELECT
                        id,
                        first_name,
                        last_name,
                        nickname,
                        personal_cash,
                        bank_cash
                    FROM npcs
                    ORDER BY personal_cash + bank_cash DESC
                    LIMIT 10
                SQL
            )->fetchAll(),
            'richest_player_gangs' => $pdo->query(
                <<<'SQL'
                    SELECT id, name, treasury, reputation
                    FROM gangs
                    ORDER BY treasury DESC
                    LIMIT 10
                SQL
            )->fetchAll(),
            'current_drug_prices' => $pdo->query(
                <<<'SQL'
                    SELECT
                        drug.name,
                        price.region,
                        price.price,
                        price.supply,
                        price.demand,
                        price.police_pressure
                    FROM drug_prices price
                    JOIN drugs drug ON drug.id = price.drug_id
                    ORDER BY price.region, drug.name
                SQL
            )->fetchAll(),
            'transaction_count' => (int) $pdo->query(
                'SELECT COUNT(*) FROM economy_transactions'
            )->fetchColumn(),
        ];
    }

    private function sumCategories(array $categories): int
    {
        $placeholders = implode(', ', array_fill(0, count($categories), '?'));
        $statement = Database::pdo()->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM economy_transactions WHERE category IN ({$placeholders})"
        );
        $statement->execute($categories);

        return (int) $statement->fetchColumn();
    }
}
