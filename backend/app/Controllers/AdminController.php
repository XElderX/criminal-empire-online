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

    public function itemCatalog(array $params, array $context): void
    {
        AdminMiddleware::ensure($context['user']);
        $pdo = Database::pdo();

        $users = $pdo->query(
            <<<'SQL'
                SELECT id, username, role, cash, bank_cash, energy, max_energy
                FROM users
                ORDER BY id ASC
            SQL
        )->fetchAll();

        $items = $pdo->query(
            <<<'SQL'
                SELECT
                    id,
                    code,
                    name,
                    category,
                    equipment_slot,
                    price,
                    illegal,
                    active,
                    'item' AS asset_type,
                    1 AS equipmentable
                FROM item_definitions
                WHERE active = 1
                ORDER BY category, name
            SQL
        )->fetchAll();

        $weapons = $pdo->query(
            <<<'SQL'
                SELECT
                    id,
                    NULL AS code,
                    name,
                    class AS category,
                    equipment_slot,
                    price,
                    illegal,
                    1 AS active,
                    'weapon' AS asset_type,
                    1 AS equipmentable
                FROM weapons
                ORDER BY price, name
            SQL
        )->fetchAll();

        $drugs = $pdo->query(
            <<<'SQL'
                SELECT
                    id,
                    NULL AS code,
                    name,
                    'drug' AS category,
                    NULL AS equipment_slot,
                    base_price AS price,
                    1 AS illegal,
                    1 AS active,
                    'drug' AS asset_type,
                    0 AS equipmentable
                FROM drugs
                ORDER BY name
            SQL
        )->fetchAll();

        Response::json([
            'users' => $users,
            'assets' => array_merge($items, $weapons, $drugs),
        ]);
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

    public function grantAsset(array $params, array $context): void
    {
        try {
            AdminMiddleware::ensure($context['user']);

            $targetUserId = (int) ($params['id'] ?? 0);
            $payload = Request::json();
            $assetType = (string) ($payload['asset_type'] ?? '');
            $assetId = (int) ($payload['asset_id'] ?? 0);
            $quantity = (int) ($payload['quantity'] ?? 1);

            if ($targetUserId <= 0) {
                throw new RuntimeException('Valid user id is required.');
            }

            if (!in_array($assetType, ['item', 'weapon', 'drug'], true)) {
                throw new RuntimeException('Asset type must be item, weapon, or drug.');
            }

            if ($assetId <= 0) {
                throw new RuntimeException('Valid asset id is required.');
            }

            if ($quantity < 1 || $quantity > 10000) {
                throw new RuntimeException('Quantity must be between 1 and 10000.');
            }

            $pdo = Database::pdo();
            $targetUser = $this->findUser($pdo, $targetUserId);
            $asset = $this->findAsset($pdo, $assetType, $assetId);

            $pdo->beginTransaction();

            try {
                $newQuantity = $this->grantInventoryAsset(
                    $pdo,
                    $assetType,
                    $targetUserId,
                    $assetId,
                    $quantity
                );

                AuditService::log(
                    (int) $context['user']['id'],
                    'admin.asset_granted',
                    [
                        'target_user_id' => $targetUserId,
                        'target_username' => $targetUser['username'],
                        'asset_type' => $assetType,
                        'asset_id' => $assetId,
                        'asset_name' => $asset['name'],
                        'quantity_added' => $quantity,
                        'new_quantity' => $newQuantity,
                    ]
                );

                $pdo->commit();

                Response::json([
                    'message' => 'Inventory asset granted.',
                    'user_id' => $targetUserId,
                    'asset_type' => $assetType,
                    'asset_id' => $assetId,
                    'asset_name' => $asset['name'],
                    'quantity_added' => $quantity,
                    'new_quantity' => $newQuantity,
                ]);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }
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

    /** @return array<string, mixed> */
    private function findAsset(\PDO $pdo, string $assetType, int $assetId): array
    {
        return match ($assetType) {
            'item' => $this->fetchSingleRow(
                $pdo,
                'SELECT id, name FROM item_definitions WHERE id = ? AND active = 1 LIMIT 1',
                [$assetId],
                'Item not found.'
            ),
            'weapon' => $this->fetchSingleRow(
                $pdo,
                'SELECT id, name FROM weapons WHERE id = ? LIMIT 1',
                [$assetId],
                'Weapon not found.'
            ),
            'drug' => $this->fetchSingleRow(
                $pdo,
                'SELECT id, name FROM drugs WHERE id = ? LIMIT 1',
                [$assetId],
                'Drug not found.'
            ),
            default => throw new RuntimeException('Unsupported asset type.'),
        };
    }

    private function grantInventoryAsset(
        \PDO $pdo,
        string $assetType,
        int $userId,
        int $assetId,
        int $quantity
    ): int {
        switch ($assetType) {
            case 'item':
                $pdo->prepare(
                    <<<'SQL'
                        INSERT INTO user_items (
                            user_id,
                            item_definition_id,
                            quantity,
                            created_at,
                            updated_at
                        ) VALUES (?, ?, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            quantity = quantity + VALUES(quantity),
                            updated_at = NOW()
                    SQL
                )->execute([$userId, $assetId, $quantity]);

                return $this->inventoryQuantity(
                    $pdo,
                    'user_items',
                    'item_definition_id',
                    $userId,
                    $assetId
                );

            case 'weapon':
                $pdo->prepare(
                    <<<'SQL'
                        INSERT INTO user_weapons (
                            user_id,
                            weapon_id,
                            quantity,
                            created_at,
                            updated_at
                        ) VALUES (?, ?, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            quantity = quantity + VALUES(quantity),
                            updated_at = NOW()
                    SQL
                )->execute([$userId, $assetId, $quantity]);

                return $this->inventoryQuantity(
                    $pdo,
                    'user_weapons',
                    'weapon_id',
                    $userId,
                    $assetId
                );

            case 'drug':
                $pdo->prepare(
                    <<<'SQL'
                        INSERT INTO user_drugs (
                            user_id,
                            drug_id,
                            quantity
                        ) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            quantity = quantity + VALUES(quantity)
                    SQL
                )->execute([$userId, $assetId, $quantity]);

                return $this->inventoryQuantity(
                    $pdo,
                    'user_drugs',
                    'drug_id',
                    $userId,
                    $assetId
                );

            default:
                throw new RuntimeException('Unsupported asset type.');
        }
    }

    private function inventoryQuantity(
        \PDO $pdo,
        string $table,
        string $column,
        int $userId,
        int $assetId
    ): int {
        $statement = $pdo->prepare(
            "SELECT quantity FROM {$table} WHERE user_id = ? AND {$column} = ? LIMIT 1"
        );
        $statement->execute([$userId, $assetId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function fetchSingleRow(
        \PDO $pdo,
        string $sql,
        array $params,
        string $missingMessage
    ): array {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        if (!$row) {
            throw new RuntimeException($missingMessage);
        }

        return $row;
    }
}
