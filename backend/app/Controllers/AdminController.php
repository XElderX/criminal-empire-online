<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\AdminMiddleware;
use App\Services\AuditService;
use RuntimeException;
use Throwable;

final class AdminController
{
    public function dashboard(array $params, array $context): void
    {
        AdminMiddleware::ensure($context['user']);
        $pdo = Database::pdo();

        Response::json([
            'stats' => [
                'users' => $this->countRows($pdo, 'users'),
                'gangs' => $this->countRows($pdo, 'gangs'),
                'territories' => $this->countRows($pdo, 'territories'),
                'crime_logs' => $this->countRows($pdo, 'crime_logs'),
                'dirty_job_runs' => $this->countRows($pdo, 'dirty_job_runs'),
                'warehouses' => $this->countRows($pdo, 'player_buildings'),
            ],
        ]);
    }

    public function audit(array $params, array $context): void
    {
        AdminMiddleware::ensure($context['user']);

        $logs = Database::pdo()->query(
            'SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100'
        )->fetchAll();

        Response::json(['data' => $logs]);
    }

    public function refillEnergy(array $params, array $context): void
    {
        try {
            AdminMiddleware::ensure($context['user']);
            $targetUserId = (int) ($params['id'] ?? 0);

            if ($targetUserId <= 0) {
                throw new RuntimeException('Valid user id is required.');
            }

            $pdo = Database::pdo();
            $target = $this->findUser($pdo, $targetUserId);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET energy = max_energy, updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$targetUserId]);

            AuditService::log(
                (int) $context['user']['id'],
                'admin.energy_refill',
                [
                    'target_user_id' => $targetUserId,
                    'target_username' => $target['username'],
                    'previous_energy' => (int) $target['energy'],
                    'max_energy' => (int) $target['max_energy'],
                ]
            );

            Response::json([
                'message' => 'Energy refilled.',
                'user_id' => $targetUserId,
                'energy' => (int) $target['max_energy'],
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function setCash(array $params, array $context): void
    {
        try {
            AdminMiddleware::ensure($context['user']);
            $targetUserId = (int) ($params['id'] ?? 0);
            $payload = Request::json();
            $amount = array_key_exists('amount', $payload)
                ? (int) $payload['amount']
                : null;

            if ($targetUserId <= 0) {
                throw new RuntimeException('Valid user id is required.');
            }

            if ($amount === null || $amount < 0) {
                throw new RuntimeException('Valid cash amount is required.');
            }

            $pdo = Database::pdo();
            $target = $this->findUser($pdo, $targetUserId);

            $pdo->prepare(
                'UPDATE users SET cash = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$amount, $targetUserId]);

            AuditService::log(
                (int) $context['user']['id'],
                'admin.cash_set',
                [
                    'target_user_id' => $targetUserId,
                    'target_username' => $target['username'],
                    'previous_cash' => (int) $target['cash'],
                    'new_cash' => $amount,
                ]
            );

            Response::json([
                'message' => 'Cash updated.',
                'user_id' => $targetUserId,
                'cash' => $amount,
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    private function countRows(\PDO $pdo, string $table): int
    {
        return (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function findUser(\PDO $pdo, int $userId): array
    {
        $statement = $pdo->prepare(
            <<<'SQL'
                SELECT id, username, cash, energy, max_energy
                FROM users
                WHERE id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        return $user;
    }
}
