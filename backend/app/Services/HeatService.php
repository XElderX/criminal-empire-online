<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class HeatService
{
    public function layLow(array $user): array
    {
        $energyCost = 12;
        $heatReduction = 3;
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $cooldownStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT created_at
                    FROM heat_actions
                    WHERE user_id = ?
                      AND action_code = 'lay_low'
                    ORDER BY id DESC
                    LIMIT 1
                SQL
            );
            $cooldownStatement->execute([$user['id']]);
            $lastAction = $cooldownStatement->fetchColumn();

            if ($lastAction && strtotime($lastAction) > time() - 60) {
                throw new RuntimeException('You must wait before lying low again.');
            }

            $userStatement = $pdo->prepare(
                'SELECT * FROM users WHERE id = ? FOR UPDATE'
            );
            $userStatement->execute([$user['id']]);
            $freshUser = $userStatement->fetch();

            if ((int) $freshUser['heat'] <= 0) {
                throw new RuntimeException('The player has no heat to reduce.');
            }

            if ((int) $freshUser['energy'] < $energyCost) {
                throw new RuntimeException('Not enough energy to lie low.');
            }

            $actualReduction = min($heatReduction, (int) $freshUser['heat']);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET
                        heat = GREATEST(0, heat - ?),
                        energy = energy - ?,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $actualReduction,
                $energyCost,
                $freshUser['id'],
            ]);

            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO heat_actions (
                        user_id,
                        action_code,
                        heat_reduced,
                        energy_cost,
                        cash_cost,
                        created_at
                    ) VALUES (?, 'lay_low', ?, ?, 0, NOW())
                SQL
            )->execute([
                $freshUser['id'],
                $actualReduction,
                $energyCost,
            ]);

            $pdo->commit();

            return [
                'message' => 'You stayed out of sight and reduced police attention.',
                'heat_reduced' => $actualReduction,
                'energy_spent' => $energyCost,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function processDecay(): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE users
                SET heat = GREATEST(0, heat - 1), updated_at = NOW()
                WHERE heat > 0
            SQL
        );
        $statement->execute();

        return ['players_cooled' => $statement->rowCount()];
    }
}
