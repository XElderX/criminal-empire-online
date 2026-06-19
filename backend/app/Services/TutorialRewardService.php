<?php

namespace App\Services;

use App\Core\Database;

final class TutorialRewardService
{
    public function grantOnce(int $userId, string $tutorialKey, string $version, array $rewardPayload): array
    {
        $cash = max(0, (int) ($rewardPayload['cash'] ?? 0));
        $xp = max(0, (int) ($rewardPayload['xp'] ?? 0));

        if ($cash === 0 && $xp === 0) {
            return ['cash' => 0, 'xp' => 0];
        }

        $rewardCode = $tutorialKey . ':' . $version . ':completion_reward';
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT rewards_claimed
                FROM user_tutorial_progress
                WHERE user_id = ?
                FOR UPDATE
            SQL
        );
        $statement->execute([$userId]);
        $json = (string) ($statement->fetchColumn() ?: '[]');
        $claimed = json_decode($json, true);
        $claimed = is_array($claimed) ? array_values($claimed) : [];

        if (in_array($rewardCode, $claimed, true)) {
            return ['cash' => 0, 'xp' => 0];
        }

        $claimed[] = $rewardCode;

        if ($cash > 0 || $xp > 0) {
            Database::pdo()->prepare(
                'UPDATE users SET cash = cash + ?, experience = experience + ?, updated_at = NOW() WHERE id = ?'
            )->execute([$cash, $xp, $userId]);
        }

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE user_tutorial_progress
                SET rewards_claimed = ?, updated_at = NOW()
                WHERE user_id = ?
            SQL
        )->execute([json_encode($claimed), $userId]);

        if ($cash > 0) {
            (new EconomyLedgerService())->record(
                'tutorial_reward',
                $cash,
                'Modest tutorial guidance reward',
                [
                    'source_type' => 'tutorial',
                    'destination_type' => 'player',
                    'destination_id' => $userId,
                    'user_id' => $userId,
                ]
            );
        }

        return ['cash' => $cash, 'xp' => $xp];
    }
}
