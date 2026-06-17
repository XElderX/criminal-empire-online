<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\EconomyLedgerService;
use RuntimeException;
use Throwable;

final class ShopController
{
    public function weapons(array $params = [], array $context = []): void
    {
        $weapons = Database::pdo()->query(
            'SELECT * FROM weapons ORDER BY price ASC'
        )->fetchAll();

        foreach ($weapons as &$weapon) {
            $weapon['effects'] = json_decode(
                (string) ($weapon['effects'] ?? ''),
                true
            ) ?: [];
        }

        Response::json(['data' => $weapons]);
    }

    public function inventory(array $params, array $context): void
    {
        $pdo = Database::pdo();

        $weaponStatement = $pdo->prepare(
            <<<'SQL'
                SELECT inventory.quantity, weapon.*
                FROM user_weapons inventory
                JOIN weapons weapon ON weapon.id = inventory.weapon_id
                WHERE inventory.user_id = ?
            SQL
        );
        $weaponStatement->execute([$context['user']['id']]);

        $drugStatement = $pdo->prepare(
            <<<'SQL'
                SELECT inventory.quantity, drug.*
                FROM user_drugs inventory
                JOIN drugs drug ON drug.id = inventory.drug_id
                WHERE inventory.user_id = ?
            SQL
        );
        $drugStatement->execute([$context['user']['id']]);

        Response::json([
            'weapons' => $weaponStatement->fetchAll(),
            'drugs' => $drugStatement->fetchAll(),
        ]);
    }

    public function buyWeapon(array $params, array $context): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $weapon = $this->findWeapon((int) $params['id']);
            $user = $this->lockUser((int) $context['user']['id']);

            if ((int) $user['cash'] < (int) $weapon['price']) {
                throw new RuntimeException('Not enough cash.');
            }

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET cash = cash - ?, updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $weapon['price'],
                $user['id'],
            ]);

            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO user_weapons (
                        user_id,
                        weapon_id,
                        quantity,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        quantity = quantity + 1,
                        updated_at = NOW()
                SQL
            )->execute([
                $user['id'],
                $weapon['id'],
            ]);

            (new EconomyLedgerService())->record(
                'equipment_purchase',
                (int) $weapon['price'],
                "Purchased weapon: {$weapon['name']}",
                [
                    'source_type' => 'player',
                    'source_id' => $user['id'],
                    'destination_type' => 'npc_weapon_shop',
                    'user_id' => $user['id'],
                ]
            );

            AuditService::log(
                (int) $user['id'],
                'shop.buy_weapon',
                ['weapon_id' => $weapon['id']]
            );

            $pdo->commit();

            Response::json([
                'message' => 'Weapon purchased.',
                'weapon' => $weapon['name'],
            ]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    private function findWeapon(int $weaponId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM weapons WHERE id = ?'
        );
        $statement->execute([$weaponId]);
        $weapon = $statement->fetch();

        if (!$weapon) {
            throw new RuntimeException('Weapon not found.');
        }

        return $weapon;
    }

    private function lockUser(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM users WHERE id = ? FOR UPDATE'
        );
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user) {
            throw new RuntimeException('Player not found.');
        }

        return $user;
    }
}
