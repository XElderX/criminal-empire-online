<?php

namespace App\Services;

use App\Core\Database;

final class SkillProgressionService
{
    private const MAX_SKILL = 100;

    public function maybeImprovePlayer(
        int $userId,
        string $skillCode,
        int $difficulty,
        int $roll,
        string $sourceType,
        ?int $sourceId,
        string $reason
    ): ?array {
        $skillCode = $this->mapPlayerSkill($skillCode);

        if ($skillCode === null) {
            return null;
        }

        $chance = max(1, min(18, (int) floor($difficulty / 2)));
        if ($roll > $chance) {
            return null;
        }

        $pdo = Database::pdo();
        $statement = $pdo->prepare("SELECT {$skillCode} FROM users WHERE id = ? FOR UPDATE");
        $statement->execute([$userId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $before = (int) $row[$skillCode];
        if ($before >= self::MAX_SKILL || ($difficulty <= 2 && $before >= 35)) {
            return null;
        }

        $gain = $difficulty >= 7 && $roll === 1 ? 2 : 1;
        $after = min(self::MAX_SKILL, $before + $gain);

        $pdo->prepare("UPDATE users SET {$skillCode} = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$after, $userId]);

        $this->log($userId, null, $sourceType, $sourceId, $skillCode, $after - $before, $before, $after, $reason);

        return [
            'target_type' => 'player',
            'user_id' => $userId,
            'skill' => $skillCode,
            'amount' => $after - $before,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ];
    }

    public function maybeImproveCrew(
        int $userId,
        int $gangMemberId,
        string $skillCode,
        int $difficulty,
        int $roll,
        string $sourceType,
        ?int $sourceId,
        string $reason
    ): ?array {
        $skillCode = $this->mapCrewSkill($skillCode);

        if ($skillCode === null) {
            return null;
        }

        $chance = max(1, min(22, (int) floor($difficulty / 2)));
        if ($roll > $chance) {
            return null;
        }

        $pdo = Database::pdo();
        $statement = $pdo->prepare(
            "SELECT {$skillCode} FROM player_gang_members WHERE id = ? AND user_id = ? FOR UPDATE"
        );
        $statement->execute([$gangMemberId, $userId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $before = (int) $row[$skillCode];
        if ($before >= self::MAX_SKILL || ($difficulty <= 2 && $before >= 45)) {
            return null;
        }

        $gain = $difficulty >= 7 && $roll === 1 ? 2 : 1;
        $after = min(self::MAX_SKILL, $before + $gain);

        $pdo->prepare("UPDATE player_gang_members SET {$skillCode} = ?, updated_at = NOW() WHERE id = ? AND user_id = ?")
            ->execute([$after, $gangMemberId, $userId]);

        $this->log($userId, $gangMemberId, $sourceType, $sourceId, $skillCode, $after - $before, $before, $after, $reason);

        $pdo->prepare(
            <<<'SQL'
                INSERT INTO crew_history (
                    gang_member_id,
                    user_id,
                    event_type,
                    title,
                    description,
                    metadata,
                    created_at
                ) VALUES (?, ?, 'skill_gain', ?, ?, ?, NOW())
            SQL
        )->execute([
            $gangMemberId,
            $userId,
            'Rare skill improvement',
            "Improved {$skillCode} from {$before} to {$after}.",
            json_encode([
                'skill' => $skillCode,
                'before' => $before,
                'after' => $after,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ], JSON_THROW_ON_ERROR),
        ]);

        return [
            'target_type' => 'crew',
            'gang_member_id' => $gangMemberId,
            'skill' => $skillCode,
            'amount' => $after - $before,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ];
    }

    private function mapPlayerSkill(string $skillCode): ?string
    {
        return match ($skillCode) {
            'stealth', 'driving', 'street_knowledge' => 'intelligence',
            'intimidation', 'discipline' => 'charisma',
            'endurance' => 'strength',
            default => in_array($skillCode, ['strength', 'intelligence', 'charisma', 'combat', 'leadership'], true)
                ? $skillCode
                : null,
        };
    }

    private function mapCrewSkill(string $skillCode): ?string
    {
        $allowed = [
            'strength',
            'shooting',
            'driving',
            'intelligence',
            'stealth',
            'intimidation',
            'discipline',
            'street_knowledge',
            'endurance',
        ];

        return in_array($skillCode, $allowed, true) ? $skillCode : null;
    }

    private function log(
        ?int $userId,
        ?int $gangMemberId,
        string $sourceType,
        ?int $sourceId,
        string $skillCode,
        int $amount,
        int $before,
        int $after,
        string $reason
    ): void {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO skill_progression_logs (
                    user_id,
                    gang_member_id,
                    source_type,
                    source_id,
                    skill_code,
                    amount,
                    value_before,
                    value_after,
                    reason,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            $userId,
            $gangMemberId,
            $sourceType,
            $sourceId,
            $skillCode,
            $amount,
            $before,
            $after,
            $reason,
        ]);
    }
}
