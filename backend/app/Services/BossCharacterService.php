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

    public function asCrewMember(array|int $user): array
    {
        $userId = is_array($user) ? (int) $user['id'] : (int) $user;
        $boss = $this->ensureProfile($userId);

        return (new CrewPresentationService())->present([
            'id' => 0,
            'member_type' => 'boss',
            'is_boss' => true,
            'npc_id' => 0,
            'first_name' => $this->firstName($boss['name']),
            'last_name' => $this->lastName($boss['name']),
            'nickname' => 'Boss',
            'gender' => $boss['gender'] ?? null,
            'age' => (int) ($boss['age'] ?? 33),
            'portrait_set_key' => $boss['portrait_set_key'] ?? null,
            'portrait_stage_cache' => null,
            'portrait_focal_x' => 50,
            'portrait_focal_y' => 40,
            'role_code' => $boss['role_code'] ?? 'leader',
            'occupation' => 'Player boss',
            'territory_name' => 'Home district',
            'biography' => 'The player-controlled boss. This character can now be selected for crimes and has operational stats like crew members.',
            'background' => 'Player character',
            'personal_cash' => 0,
            'salary_weekly' => 0,
            'unpaid_salary' => 0,
            'health' => $boss['health'],
            'max_health' => $boss['max_health'],
            'morale' => 100,
            'loyalty' => 100,
            'personal_heat' => $boss['personal_heat'],
            'under_investigation' => false,
            'sent_away_until' => null,
            'revenge_risk' => 0,
            'revenge_status' => 'none',
            'status' => $boss['status'],
            'level' => $boss['level'],
            'experience' => $boss['experience'],
            'criminal_reputation' => $boss['reputation'],
            'strength' => $boss['skills']['strength'],
            'shooting' => $boss['skills']['shooting'],
            'driving' => $boss['skills']['driving'],
            'intelligence' => $boss['skills']['intelligence'],
            'stealth' => $boss['skills']['stealth'],
            'intimidation' => $boss['skills']['intimidation'],
            'discipline' => $boss['skills']['discipline'],
            'street_knowledge' => $boss['skills']['street_knowledge'],
            'endurance' => $boss['skills']['endurance'],
            'jobs_completed' => 0,
            'jobs_failed' => 0,
            'arrests' => $boss['arrested_until'] ? 1 : 0,
            'injuries' => $boss['injury_status'] ? 1 : 0,
            'total_earnings' => 0,
            'recovery_until' => null,
            'arrested_until' => $boss['arrested_until'],
            'traits' => [],
            'equipment' => [],
            'recent_history' => $this->history($userId),
        ]);
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
            'age' => (int) ($user['boss_age'] ?? 33),
            'gender' => $user['boss_gender'] ?? null,
            'portrait_set_key' => $user['boss_portrait_set_key'] ?? null,
            'role_code' => $user['boss_role_code'] ?? 'leader',
            'reputation' => (int) ($user['reputation'] ?? 0),
            'skills' => [
                'strength' => (int) ($user['strength'] ?? 1) * 10,
                'shooting' => (int) ($user['boss_shooting'] ?? ((int) ($user['combat'] ?? 1) * 10)),
                'driving' => (int) ($user['boss_driving'] ?? ((int) ($user['intelligence'] ?? 1) * 8)),
                'intelligence' => (int) ($user['intelligence'] ?? 1) * 10,
                'stealth' => (int) ($user['boss_stealth'] ?? ((int) ($user['intelligence'] ?? 1) * 8)),
                'intimidation' => (int) ($user['boss_intimidation'] ?? ((int) ($user['charisma'] ?? 1) * 8)),
                'discipline' => (int) ($user['boss_discipline'] ?? ((int) ($user['leadership'] ?? 1) * 8)),
                'street_knowledge' => (int) ($user['boss_street_knowledge'] ?? ((int) ($user['intelligence'] ?? 1) * 8)),
                'endurance' => (int) ($user['boss_endurance'] ?? ((int) ($user['strength'] ?? 1) * 8)),
            ],
        ];
    }

    private function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? 'Boss';
    }

    private function lastName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Character';
    }
}
