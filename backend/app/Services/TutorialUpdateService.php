<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;

final class TutorialUpdateService
{
    public function ensureCurrentProgress(int $userId): void
    {
        $progress = $this->progress($userId);

        if ($progress === null) {
            return;
        }

        $status = (string) $progress['status'];
        $completedVersion = (string) ($progress['completed_tutorial_version'] ?? '');
        $tutorialKey = (string) ($progress['tutorial_key'] ?? GameConfig::TUTORIAL_KEY_FULL);
        $completedUpdates = $this->decodeList($progress['completed_update_tutorial_versions'] ?? '[]');
        $dismissedUpdates = $this->decodeList($progress['dismissed_update_tutorial_versions'] ?? '[]');

        if (
            $status === 'completed'
            && $completedVersion !== GameConfig::TUTORIAL_VERSION
            && !in_array(GameConfig::TUTORIAL_VERSION, $completedUpdates, true)
            && !in_array(GameConfig::TUTORIAL_VERSION, $dismissedUpdates, true)
        ) {
            $this->startUpdateTutorial($userId);
            return;
        }

        if ($status === 'active' && $tutorialKey === '') {
            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE user_tutorial_progress
                    SET tutorial_key = ?, tutorial_version = ?, updated_at = NOW()
                    WHERE user_id = ?
                SQL
            )->execute([GameConfig::TUTORIAL_KEY_FULL, GameConfig::TUTORIAL_VERSION, $userId]);
        }
    }

    private function startUpdateTutorial(int $userId): void
    {
        $firstStep = GameConfig::tutorialSteps(GameConfig::TUTORIAL_KEY_UPDATE)[0]['code'];

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE user_tutorial_progress
                SET
                    tutorial_key = ?,
                    tutorial_version = ?,
                    status = 'active',
                    current_step_code = ?,
                    completed_steps = JSON_ARRAY(),
                    skipped_at = NULL,
                    completed_at = NULL,
                    started_at = NOW(),
                    updated_at = NOW()
                WHERE user_id = ?
            SQL
        )->execute([
            GameConfig::TUTORIAL_KEY_UPDATE,
            GameConfig::TUTORIAL_VERSION,
            $firstStep,
            $userId,
        ]);
    }

    private function progress(int $userId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM user_tutorial_progress WHERE user_id = ? LIMIT 1'
        );
        $statement->execute([$userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private function decodeList(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
