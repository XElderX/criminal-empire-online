<?php
namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class CrimeService
{
    public function commit(array $user, int $crimeId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$user['id']]);
        $freshUser = $stmt->fetch();
        $stmt = $pdo->prepare('SELECT * FROM crimes WHERE id = ? LIMIT 1');
        $stmt->execute([$crimeId]);
        $crime = $stmt->fetch();
        if (!$crime) throw new RuntimeException('Crime not found');
        if ((int)$freshUser['energy'] < (int)$crime['energy_cost']) throw new RuntimeException('Not enough energy');
        $successChance = min(95, max(5, (int)$crime['success_rate'] + (int)$freshUser['intelligence']));
        $success = random_int(1, 100) <= $successChance;
        $reward = $success ? random_int((int)$crime['reward_min'], (int)$crime['reward_max']) : 0;
        $pdo->prepare('UPDATE users SET cash = cash + ?, energy = energy - ?, heat = heat + ?, experience = experience + ?, updated_at = NOW() WHERE id = ?')
            ->execute([$reward, $crime['energy_cost'], $crime['heat_gain'], $crime['experience_gain'], $freshUser['id']]);
        $pdo->prepare('INSERT INTO crime_logs (user_id, crime_id, success, reward, heat_gained, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([$freshUser['id'], $crime['id'], $success ? 1 : 0, $reward, $crime['heat_gain']]);
        AuditService::log((int)$freshUser['id'], 'crime.commit', ['crime_id' => $crimeId, 'success' => $success, 'reward' => $reward]);
        $pdo->commit();
        return ['success' => $success, 'reward' => $reward, 'heat_gained' => (int)$crime['heat_gain']];
    }
}
