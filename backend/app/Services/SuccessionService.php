<?php

namespace App\Services;

use App\Core\Database;

final class SuccessionService
{
    public function triggerSuccession(int $userId, string $sourceType, ?int $sourceId, string $notes): array
    {
        $pdo = Database::pdo();
        $candidate = $this->bestCandidate($userId);

        if (!$candidate) {
            $fallbackName = 'Fallback Successor';
            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET
                        boss_display_name = ?,
                        boss_status = 'active',
                        boss_alive = 1,
                        boss_health = 80,
                        boss_max_health = 100,
                        boss_injury_status = NULL,
                        boss_successor_member_id = NULL,
                        boss_rank = 'Street Hustler',
                        boss_personal_heat = GREATEST(0, boss_personal_heat - 20),
                        gang_heat = GREATEST(0, gang_heat - 10),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$fallbackName, $userId]);

            (new BossCharacterService())->recordHistory($userId, 'succession', 'Fallback successor installed', 'No eligible crew member was available, so a fallback successor kept the save playable.', [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'notes' => $notes,
            ]);

            return [
                'succession_triggered' => true,
                'successor_type' => 'fallback',
                'successor_name' => $fallbackName,
            ];
        }

        $successorName = trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
        if (!empty($candidate['nickname'])) {
            $successorName .= " '" . $candidate['nickname'] . "'";
        }

        $pdo->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    boss_display_name = ?,
                    boss_status = 'active',
                    boss_alive = 1,
                    boss_health = ?,
                    boss_max_health = ?,
                    boss_injury_status = NULL,
                    boss_successor_member_id = ?,
                    boss_rank = ?,
                    boss_personal_heat = GREATEST(0, boss_personal_heat - 25),
                    gang_heat = GREATEST(0, gang_heat - 12),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([
            $successorName,
            max(60, (int) $candidate['health']),
            max(100, (int) $candidate['max_health']),
            $candidate['id'],
            (new BossCharacterService())->rankForLevel((int) $candidate['level']),
            $userId,
        ]);

        $pdo->prepare(
            "UPDATE player_gang_members SET status = 'dismissed', dismissal_reason = 'Promoted to boss after succession', dismissed_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?"
        )->execute([$candidate['id'], $userId]);

        (new BossCharacterService())->recordHistory($userId, 'succession', 'New boss selected', "{$successorName} became the new boss after the previous boss died.", [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'successor_member_id' => (int) $candidate['id'],
            'selection_reason' => 'highest eligible level, then leadership/intelligence/loyalty/tenure',
            'notes' => $notes,
        ], (int) $candidate['id']);

        return [
            'succession_triggered' => true,
            'successor_type' => 'crew',
            'successor_member_id' => (int) $candidate['id'],
            'successor_name' => $successorName,
        ];
    }

    public function bestCandidate(int $userId): ?array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT member.*, npc.first_name, npc.last_name, npc.nickname
                FROM player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                WHERE member.user_id = ?
                  AND member.status = 'active'
                  AND member.health > 0
                  AND (member.sent_away_until IS NULL OR member.sent_away_until <= NOW())
                ORDER BY
                  member.level DESC,
                  member.intelligence DESC,
                  member.loyalty DESC,
                  member.recruited_at ASC,
                  member.personal_heat ASC
                LIMIT 1
            SQL
        );
        $statement->execute([$userId]);
        $candidate = $statement->fetch();

        return $candidate ?: null;
    }
}
