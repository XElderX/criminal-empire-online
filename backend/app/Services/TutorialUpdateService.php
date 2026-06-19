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
        $requiredVersion = GameConfig::TUTORIAL_UPDATE_TRIGGER_VERSION;

        // Patch releases such as v0.6.5.1 should not reopen tutorial panels for
        // players who already completed the v0.6.4 world tutorial/update path.
        if (
            $status === 'active'
            && $tutorialKey === GameConfig::TUTORIAL_KEY_UPDATE
            && $this->versionAtLeast($completedVersion, $requiredVersion)
        ) {
            $this->markUpdateNotNeeded($userId, $completedUpdates);
            return;
        }

        if (
            $status === 'completed'
            && !$this->versionAtLeast($completedVersion, $requiredVersion)
            && !$this->versionListContainsAtLeast($completedUpdates, $requiredVersion)
            && !$this->versionListContainsAtLeast($dismissedUpdates, $requiredVersion)
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

    private function markUpdateNotNeeded(int $userId, array $completedUpdates): void
    {
        foreach ([GameConfig::TUTORIAL_UPDATE_TRIGGER_VERSION, GameConfig::TUTORIAL_VERSION] as $version) {
            if (!in_array($version, $completedUpdates, true)) {
                $completedUpdates[] = $version;
            }
        }

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE user_tutorial_progress
                SET
                    tutorial_key = ?,
                    tutorial_version = ?,
                    status = 'completed',
                    current_step_code = 'completed',
                    completed_update_tutorial_versions = ?,
                    completed_at = COALESCE(completed_at, NOW()),
                    updated_at = NOW()
                WHERE user_id = ?
            SQL
        )->execute([
            GameConfig::TUTORIAL_KEY_FULL,
            GameConfig::TUTORIAL_VERSION,
            json_encode(array_values($completedUpdates)),
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

    private function versionListContainsAtLeast(array $versions, string $minimumVersion): bool
    {
        foreach ($versions as $version) {
            if (is_string($version) && $this->versionAtLeast($version, $minimumVersion)) {
                return true;
            }
        }

        return false;
    }

    private function versionAtLeast(string $version, string $minimumVersion): bool
    {
        if ($version === '') {
            return false;
        }

        return version_compare($version, $minimumVersion, '>=');
    }
}
