<?php
namespace App\Services;

use App\Core\Database;

final class EconomyLedgerService
{
    public function record(string $category, int $amount, string $description, array $context = []): void
    {
        $sql = 'INSERT INTO economy_transactions (category,amount,currency,source_type,source_id,destination_type,destination_id,user_id,npc_id,business_id,gang_member_id,job_run_id,territory_id,description,game_date,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())';
        Database::pdo()->prepare($sql)->execute([
            $category, max(0, $amount), $context['currency'] ?? 'cash',
            $context['source_type'] ?? null, $context['source_id'] ?? null,
            $context['destination_type'] ?? null, $context['destination_id'] ?? null,
            $context['user_id'] ?? null, $context['npc_id'] ?? null,
            $context['business_id'] ?? null, $context['gang_member_id'] ?? null,
            $context['job_run_id'] ?? null, $context['territory_id'] ?? null,
            $description,
        ]);
    }
}
