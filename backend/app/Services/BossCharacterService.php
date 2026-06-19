<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class BossCharacterService
{
    public function ensureProfile(int $userId): array
    {
        $pdo = Database::pdo();
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user) {
            throw new RuntimeException('Boss profile not found.');
        }

        if (empty($user['boss_display_name'])) {
            $displayName = $user['username'] ?: 'The Boss';
            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET
                        boss_display_name = ?,
                        boss_health = COALESCE(NULLIF(boss_health, 0), 100),
                        boss_max_health = COALESCE(NULLIF(boss_max_health, 0), 100),
                        boss_rank = ?,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $displayName,
                $this->rankForLevel((int) ($user['level'] ?? 1)),
                $userId,
            ]);

            $statement->execute([$userId]);
            $user = $statement->fetch();
        }

        return $this->formatBoss($user);
    }

    public function profile(array $user): array
    {
        return $this->ensureProfile((int) $user['id']);
    }

    public function history(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM boss_history
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT 60
            SQL
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function recordHistory(
        int $userId,
        string $eventType,
        string $title,
        string $description,
        array $metadata = [],
        ?int $gangMemberId = null
    ): void {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO boss_history (
                    user_id,
                    gang_member_id,
                    event_type,
                    title,
                    description,
                    metadata,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            $userId,
            $gangMemberId,
            $eventType,
            $title,
            $description,
            json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    public function injureBoss(int $userId, string $severity, string $sourceType, ?int $sourceId, string $notes): array
    {
        $damage = match ($severity) {
            'minor' => 8,
            'moderate' => 18,
            'serious' => 34,
            'critical' => 55,
            default => 10,
        };

        $pdo = Database::pdo();
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user) {
            throw new RuntimeException('Boss not found.');
        }

        $before = (int) ($user['boss_health'] ?? 100);
        $after = max(0, $before - $damage);
        $alive = $after > 0 ? 1 : 0;
        $status = $alive ? 'injured' : 'dead';

        $pdo->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    boss_health = ?,
                    boss_injury_status = ?,
                    boss_status = ?,
                    boss_alive = ?,
                    boss_dead_at = IF(? = 0, NOW(), boss_dead_at),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$after, $severity, $status, $alive, $alive, $userId]);

        $this->recordHistory($userId, 'boss_injury', 'Boss injured', $notes, [
            'severity' => $severity,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'health_before' => $before,
            'health_after' => $after,
        ]);

        if (!$alive) {
            return (new SuccessionService())->triggerSuccession($userId, $sourceType, $sourceId, $notes);
        }

        return [
            'boss_alive' => true,
            'health_before' => $before,
            'health_after' => $after,
            'severity' => $severity,
        ];
    }

    public function arrestBoss(int $userId, int $hours, string $reason): array
    {
        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    boss_status = 'arrested',
                    boss_arrested_until = DATE_ADD(NOW(), INTERVAL ? HOUR),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$hours, $userId]);

        $this->recordHistory($userId, 'boss_arrest', 'Boss arrested', $reason, [
            'hours' => $hours,
        ]);

        return [
            'boss_status' => 'arrested',
            'arrest_hours' => $hours,
            'reason' => $reason,
        ];
    }

    public function rankForLevel(int $level): string
    {
        return match (true) {
            $level >= 8 => 'Kingpin',
            $level >= 7 => 'City Power',
            $level >= 6 => 'Underworld Figure',
            $level >= 5 => 'Neighborhood Boss',
            $level >= 4 => 'Crew Boss',
            $level >= 3 => 'Local Operator',
            $level >= 2 => 'Street Hustler',
            default => 'Nobody',
        };
    }

    private function formatBoss(array $user): array
    {
        $level = (int) ($user['level'] ?? 1);

        return [
            'id' => (int) $user['id'],
            'name' => $user['boss_display_name'] ?: $user['username'],
            'username' => $user['username'],
            'level' => $level,
            'experience' => (int) ($user['experience'] ?? 0),
            'rank' => $user['boss_rank'] ?: $this->rankForLevel($level),
            'health' => (int) ($user['boss_health'] ?? 100),
            'max_health' => (int) ($user['boss_max_health'] ?? 100),
            'status' => $user['boss_status'] ?? 'active',
            'injury_status' => $user['boss_injury_status'] ?? null,
            'arrested_until' => $user['boss_arrested_until'] ?? null,
            'alive' => (bool) ($user['boss_alive'] ?? true),
            'dead_at' => $user['boss_dead_at'] ?? null,
            'personal_heat' => (int) ($user['boss_personal_heat'] ?? $user['heat'] ?? 0),
            'gang_heat' => (int) ($user['gang_heat'] ?? 0),
            'display_heat' => max(
                (int) ($user['heat'] ?? 0),
                (int) ($user['boss_personal_heat'] ?? 0),
                (int) ($user['gang_heat'] ?? 0)
            ),
            'successor_member_id' => $user['boss_successor_member_id'] ?? null,
        ];
    }
}
