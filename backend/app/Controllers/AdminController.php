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
        $pdo=Database::pdo();
        Response::json(['stats'=>[
            'users'=>(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'gangs'=>(int)$pdo->query('SELECT COUNT(*) FROM gangs')->fetchColumn(),
            'territories'=>(int)$pdo->query('SELECT COUNT(*) FROM territories')->fetchColumn(),
            'crime_logs'=>(int)$pdo->query('SELECT COUNT(*) FROM crime_logs')->fetchColumn(),
        ]]);
    }
    public function audit(array $params, array $context): void
    {
        AdminMiddleware::ensure($context['user']);
        Response::json(['data'=>Database::pdo()->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100')->fetchAll()]);
    }

    public function refillEnergy(array $params, array $context): void
    {
        try {
            AdminMiddleware::ensure($context['user']);
            $targetUserId = isset($params['id']) ? (int)$params['id'] : 0;
            if ($targetUserId <= 0) {
                throw new RuntimeException('Valid user id is required');
            }

            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT id, username, energy, max_energy FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$targetUserId]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new RuntimeException('User not found');
            }

            $pdo->prepare('UPDATE users SET energy = max_energy, updated_at = NOW() WHERE id = ?')
                ->execute([$targetUserId]);
            AuditService::log((int)$context['user']['id'], 'admin.energy_refill', [
                'target_user_id' => $targetUserId,
                'target_username' => $target['username'],
                'previous_energy' => (int)$target['energy'],
                'max_energy' => (int)$target['max_energy'],
            ]);

            Response::json([
                'message' => 'Energy refilled',
                'user_id' => $targetUserId,
                'energy' => (int)$target['max_energy'],
            ]);
        } catch (Throwable $e) {
            Response::json(['message' => $e->getMessage()], 422);
        }
    }

    public function setCash(array $params, array $context): void
    {
        try {
            AdminMiddleware::ensure($context['user']);
            $targetUserId = isset($params['id']) ? (int)$params['id'] : 0;
            $payload = Request::json();
            $amount = isset($payload['amount']) ? (int)$payload['amount'] : null;
            if ($targetUserId <= 0) {
                throw new RuntimeException('Valid user id is required');
            }
            if ($amount === null || $amount < 0) {
                throw new RuntimeException('Valid cash amount is required');
            }

            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT id, username, cash FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$targetUserId]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new RuntimeException('User not found');
            }

            $pdo->prepare('UPDATE users SET cash = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$amount, $targetUserId]);
            AuditService::log((int)$context['user']['id'], 'admin.cash_set', [
                'target_user_id' => $targetUserId,
                'target_username' => $target['username'],
                'previous_cash' => (int)$target['cash'],
                'new_cash' => $amount,
            ]);

            Response::json([
                'message' => 'Cash updated',
                'user_id' => $targetUserId,
                'cash' => $amount,
            ]);
        } catch (Throwable $e) {
            Response::json(['message' => $e->getMessage()], 422);
        }
    }
}
