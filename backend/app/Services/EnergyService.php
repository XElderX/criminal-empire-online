<?php

namespace App\Services;

use App\Core\Database;

final class EnergyService
{
    public function regenerate(int $amount = 10): array
    {
        $amount = max(0, $amount);

        if ($amount === 0) {
            return ['players_regenerated' => 0, 'energy_per_player' => 0];
        }

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    energy = LEAST(max_energy, energy + ?),
                    updated_at = NOW()
                WHERE energy < max_energy
            SQL
        );
        $statement->execute([$amount]);

        return [
            'players_regenerated' => $statement->rowCount(),
            'energy_per_player' => $amount,
        ];
    }
}
