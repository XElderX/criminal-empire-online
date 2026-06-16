<?php
namespace App\Services;

use App\Core\Database;
use App\Config\GameConfig;
use RuntimeException;

final class AuthService
{
    public function register(array $data): array
    {
        foreach (['username','email','password'] as $field) {
            if (empty($data[$field])) throw new RuntimeException("{$field} is required");
        }
        $stmt = Database::pdo()->prepare('INSERT INTO users (username,email,password,role,cash,bank_cash,dirty_money,reputation,heat,energy,max_energy,home_territory_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([
            trim($data['username']),
            strtolower(trim($data['email'])),
            password_hash($data['password'], PASSWORD_ARGON2ID),
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
        $userId = (int)Database::pdo()->lastInsertId();
        (new EconomyLedgerService())->record('starting_funds', GameConfig::STARTING_CASH, 'Initial single-player starting funds', ['source_type'=>'game_start','destination_type'=>'player','destination_id'=>$userId,'user_id'=>$userId]);
        AuditService::log($userId, 'auth.register');
        return $this->issueToken($userId);
    }

    public function login(string $email, string $password): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) throw new RuntimeException('Invalid credentials');
        AuditService::log((int)$user['id'], 'auth.login');
        return $this->issueToken((int)$user['id']);
    }

    private function startingTerritoryId(): ?int
    {
        $stmt = Database::pdo()->prepare("SELECT id FROM territories WHERE name='Old Town' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function issueToken(int $userId): array
    {
        $plain = bin2hex(random_bytes(32));
        $stmt = Database::pdo()->prepare('INSERT INTO api_tokens (user_id, token_hash, created_at, expires_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))');
        $stmt->execute([$userId, hash('sha256', $plain)]);
        $user = Database::pdo()->query('SELECT id,username,email,role,cash,bank_cash,dirty_money,reputation,home_territory_id,energy,max_energy,heat,level,experience,strength,intelligence,charisma,combat,leadership FROM users WHERE id=' . $userId)->fetch();
        return ['token' => $plain, 'user' => $user];
    }
}
