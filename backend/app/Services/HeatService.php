<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class HeatService
{
    public function overview(array $user): array
    {
        return (new HeatPressureService())->overview($user);
    }

    public function logs(array $user): array
    {
        return ['data' => (new HeatPressureService())->logs((int) $user['id'], 100)];
    }

    public function reductionOptions(array $user): array
    {
        return ['data' => (new HeatPressureService())->reductionOptions($user)];
    }

    public function reduce(array $user, string $code, array $payload = []): array
    {
        return (new HeatPressureService())->reduce($user, $code, $payload);
    }

    public function processDaily(array $user, ?string $date = null): array
    {
        return (new HeatPressureService())->processDaily((int) $user['id'], $date);
    }

    public function layLow(array $user): array
    {
        return (new HeatPressureService())->reduce($user, 'lie_low_short', []);
    }

    public function processDecay(): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    heat = GREATEST(0, heat - 1),
                    boss_personal_heat = GREATEST(0, boss_personal_heat - 1),
                    gang_heat = GREATEST(0, gang_heat - 1),
                    updated_at = NOW()
                WHERE heat > 0 OR boss_personal_heat > 0 OR gang_heat > 0
            SQL
        );
        $statement->execute();

        return ['players_cooled' => $statement->rowCount()];
    }
}
