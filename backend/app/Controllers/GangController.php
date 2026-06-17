<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use RuntimeException;
use Throwable;

final class GangController
{
    public function index(array $params = [], array $context = []): void
    {
        $gangs = Database::pdo()->query(
            'SELECT * FROM gangs ORDER BY reputation DESC'
        )->fetchAll();

        Response::json(['data' => $gangs]);
    }

    public function create(array $params, array $context): void
    {
        $payload = Request::json();
        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            Response::json(['message' => 'Gang name is required.'], 422);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $createGang = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO gangs (
                        name,
                        boss_user_id,
                        treasury,
                        reputation,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, 0, 0, NOW(), NOW())
                SQL
            );
            $createGang->execute([$name, $context['user']['id']]);
            $gangId = (int) $pdo->lastInsertId();

            $createMembership = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO gang_members (
                        gang_id,
                        user_id,
                        `rank`,
                        joined_at
                    ) VALUES (?, ?, 'boss', NOW())
                SQL
            );
            $createMembership->execute([
                $gangId,
                $context['user']['id'],
            ]);

            $pdo->commit();

            Response::json(
                [
                    'message' => 'Gang created.',
                    'gang_id' => $gangId,
                ],
                201
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
