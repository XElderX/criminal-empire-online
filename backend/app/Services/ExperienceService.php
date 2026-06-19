<?php

namespace App\Services;

use App\Core\Database;

final class ExperienceService
{
    public function grantPlayer(
        int $userId,
        int $amount,
        string $sourceType,
        ?int $sourceId,
        string $reason
    ): array {
        $amount = max(0, $amount);

        if ($amount === 0) {
            return [
                'amount' => 0,
                'level_before' => 0,
                'level_after' => 0,
                'experience_before' => 0,
                'experience_after' => 0,
                'leveled_up' => false,
            ];
        }

        $pdo = Database::pdo();
        $statement = $pdo->prepare('SELECT id, level, experience FROM users WHERE id = ? FOR UPDATE');
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user) {
            return [
                'amount' => 0,
                'level_before' => 0,
                'level_after' => 0,
                'experience_before' => 0,
                'experience_after' => 0,
                'leveled_up' => false,
            ];
        }

        $levelBefore = (int) $user['level'];
        $experienceBefore = (int) $user['experience'];
        $experienceAfter = $experienceBefore + $amount;
        $levelAfter = $this->levelForExperience($experienceAfter, $levelBefore);

        $pdo->prepare(
            'UPDATE users SET experience = ?, level = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$experienceAfter, $levelAfter, $userId]);

        $this->log($userId, null, $sourceType, $sourceId, $amount, $levelBefore, $levelAfter, $experienceBefore, $experienceAfter, $reason);

        return [
            'amount' => $amount,
            'level_before' => $levelBefore,
            'level_after' => $levelAfter,
            'experience_before' => $experienceBefore,
            'experience_after' => $experienceAfter,
            'leveled_up' => $levelAfter > $levelBefore,
        ];
    }

    public function grantCrew(
        int $userId,
        int $gangMemberId,
        int $amount,
        string $sourceType,
        ?int $sourceId,
        string $reason
    ): array {
        $amount = max(0, $amount);

        if ($amount === 0) {
            return [
                'gang_member_id' => $gangMemberId,
                'amount' => 0,
                'level_before' => 0,
                'level_after' => 0,
                'experience_before' => 0,
                'experience_after' => 0,
                'leveled_up' => false,
            ];
        }

        $pdo = Database::pdo();
        $statement = $pdo->prepare(
            'SELECT id, level, experience FROM player_gang_members WHERE id = ? AND user_id = ? FOR UPDATE'
        );
        $statement->execute([$gangMemberId, $userId]);
        $member = $statement->fetch();

        if (!$member) {
            return [
                'gang_member_id' => $gangMemberId,
                'amount' => 0,
                'level_before' => 0,
                'level_after' => 0,
                'experience_before' => 0,
                'experience_after' => 0,
                'leveled_up' => false,
            ];
        }

        $levelBefore = (int) $member['level'];
        $experienceBefore = (int) $member['experience'];
        $experienceAfter = $experienceBefore + $amount;
        $levelAfter = $this->levelForExperience($experienceAfter, $levelBefore);

        $pdo->prepare(
            'UPDATE player_gang_members SET experience = ?, level = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
        )->execute([$experienceAfter, $levelAfter, $gangMemberId, $userId]);

        $this->log($userId, $gangMemberId, $sourceType, $sourceId, $amount, $levelBefore, $levelAfter, $experienceBefore, $experienceAfter, $reason);

        return [
            'gang_member_id' => $gangMemberId,
            'amount' => $amount,
            'level_before' => $levelBefore,
            'level_after' => $levelAfter,
            'experience_before' => $experienceBefore,
            'experience_after' => $experienceAfter,
            'leveled_up' => $levelAfter > $levelBefore,
        ];
    }

    public function levelForExperience(int $experience, int $minimumLevel = 1): int
    {
        $level = max(1, $minimumLevel);

        while ($experience >= $this->thresholdForLevel($level + 1)) {
            $level++;
        }

        return $level;
    }

    public function thresholdForLevel(int $level): int
    {
        $level = max(1, $level);

        return (int) (($level - 1) * ($level - 1) * 100);
    }

    private function log(
        ?int $userId,
        ?int $gangMemberId,
        string $sourceType,
        ?int $sourceId,
        int $amount,
        int $levelBefore,
        int $levelAfter,
        int $experienceBefore,
        int $experienceAfter,
        string $reason
    ): void {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO experience_logs (
                    user_id,
                    gang_member_id,
                    source_type,
                    source_id,
                    amount,
                    level_before,
                    level_after,
                    experience_before,
                    experience_after,
                    reason,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            $userId,
            $gangMemberId,
            $sourceType,
            $sourceId,
            $amount,
            $levelBefore,
            $levelAfter,
            $experienceBefore,
            $experienceAfter,
            $reason,
        ]);
    }
}
