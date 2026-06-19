<?php

namespace App\Services;

use App\Core\Database;
use Throwable;

final class CarryingRiskService
{
    public function summarize(int $userId): array
    {
        $illegalDrugUnits = $this->safeCount('SELECT COALESCE(SUM(quantity), 0) FROM user_drugs WHERE user_id = ?', $userId);
        $equippedWeapons = $this->safeCount(
            "SELECT COUNT(*) FROM user_weapons WHERE user_id = ? AND equipped = 1",
            $userId
        );

        $risk = 0;
        $warnings = [];

        if ($illegalDrugUnits > 0) {
            $risk += min(25, 5 + (int) floor($illegalDrugUnits / 10));
            $warnings[] = 'Carried contraband increases search risk while traveling.';
        }

        if ($equippedWeapons > 0) {
            $risk += 8;
            $warnings[] = 'Equipped illegal gear can draw attention in police-heavy hotspots.';
        }

        return [
            'risk_bonus' => $risk,
            'illegal_drug_units' => $illegalDrugUnits,
            'equipped_weapons' => $equippedWeapons,
            'warnings' => $warnings,
        ];
    }

    private function safeCount(string $sql, int $userId): int
    {
        try {
            $statement = Database::pdo()->prepare($sql);
            $statement->execute([$userId]);
            return (int) ($statement->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }
}
