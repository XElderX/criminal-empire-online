<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class AuthService
{
    public function register(array $data): array
    {
        $this->validateRegistration($data);

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO users (
                        username,
                        email,
                        password,
                        boss_display_name,
                        role,
                        cash,
                        bank_cash,
                        dirty_money,
                        reputation,
                        heat,
                        energy,
                        max_energy,
                        home_territory_id,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                SQL
            );

            $statement->execute([
                trim((string) $data['username']),
                strtolower(trim((string) $data['email'])),
                password_hash((string) $data['password'], PASSWORD_ARGON2ID),
                $this->bossDisplayName($data),
                'player',
                GameConfig::STARTING_CASH,
                GameConfig::STARTING_BANK_CASH,
                GameConfig::STARTING_DIRTY_MONEY,
                GameConfig::STARTING_REPUTATION,
                GameConfig::STARTING_HEAT,
                GameConfig::STARTING_ENERGY,
                GameConfig::STARTING_MAX_ENERGY,
                $this->startingTerritoryId(),
            ]);

            $userId = (int) $pdo->lastInsertId();

            (new TutorialService())->createForNewUser($userId);

            (new EconomyLedgerService())->record(
                'starting_funds',
                GameConfig::STARTING_CASH,
                'Initial single-player starting funds',
                [
                    'source_type' => 'game_start',
                    'destination_type' => 'player',
                    'destination_id' => $userId,
                    'user_id' => $userId,
                ]
            );

            AuditService::log($userId, 'auth.register');

            $tokenResponse = $this->issueToken($userId);
            $pdo->commit();

            return $tokenResponse;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function login(string $email, string $password): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM users WHERE email = ? LIMIT 1'
        );
        $statement->execute([strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            throw new RuntimeException('Invalid credentials');
        }

        AuditService::log((int) $user['id'], 'auth.login');

        return $this->issueToken((int) $user['id']);
    }

    private function validateRegistration(array $data): void
    {
        foreach (['username', 'email', 'password', 'boss_first_name', 'boss_last_name'] as $field) {
            if (empty($data[$field])) {
                throw new RuntimeException("{$field} is required");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required');
        }

        if (strlen((string) $data['password']) < 8) {
            throw new RuntimeException('Password must contain at least 8 characters');
        }

        foreach (['boss_first_name', 'boss_last_name'] as $field) {
            $value = trim((string) $data[$field]);

            if (strlen($value) < 2) {
                throw new RuntimeException("{$field} must contain at least 2 characters");
            }

            if (strlen($value) > 40) {
                throw new RuntimeException("{$field} must contain at most 40 characters");
            }
        }
    }

    private function bossDisplayName(array $data): string
    {
        return trim((string) $data['boss_first_name']) . ' ' . trim((string) $data['boss_last_name']);
    }

    private function startingTerritoryId(): ?int
    {
        $statement = Database::pdo()->prepare(
            "SELECT id FROM territories WHERE name = 'Old Town' LIMIT 1"
        );
        $statement->execute();
        $territoryId = $statement->fetchColumn();

        return $territoryId ? (int) $territoryId : null;
    }

    private function issueToken(int $userId): array
    {
        $plainToken = bin2hex(random_bytes(32));

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO api_tokens (
                    user_id,
                    token_hash,
                    created_at,
                    expires_at
                ) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
            SQL
        );

        $statement->execute([
            $userId,
            hash('sha256', $plainToken),
        ]);

        $userStatement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    id,
                    username,
                    email,
                    role,
                    cash,
                    bank_cash,
                    dirty_money,
                    reputation,
                    home_territory_id,
                    energy,
                    max_energy,
                    heat,
                    boss_personal_heat,
                    gang_heat,
                    boss_health,
                    boss_max_health,
                    boss_status,
                    boss_rank,
                    boss_alive,
                    level,
                    experience,
                    strength,
                    intelligence,
                    charisma,
                    combat,
                    leadership
                FROM users
                WHERE id = ?
            SQL
        );
        $userStatement->execute([$userId]);

        return [
            'token' => $plainToken,
            'user' => $userStatement->fetch(),
            'version' => GameConfig::VERSION,
        ];
    }
}
