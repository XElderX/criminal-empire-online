<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class TutorialService
{
    public function createForNewUser(int $userId): void
    {
        $firstStep = GameConfig::tutorialSteps(GameConfig::TUTORIAL_KEY_FULL)[0]['code'];

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO user_tutorial_progress (
                    user_id,
                    tutorial_key,
                    tutorial_version,
                    status,
                    current_step_code,
                    completed_steps,
                    rewards_claimed,
                    completed_update_tutorial_versions,
                    dismissed_update_tutorial_versions,
                    started_at,
                    last_seen_at,
                    updated_at
                ) VALUES (?, ?, ?, 'active', ?, JSON_ARRAY(), JSON_ARRAY(), JSON_ARRAY(), JSON_ARRAY(), NOW(), NOW(), NOW())
            SQL
        )->execute([
            $userId,
            GameConfig::TUTORIAL_KEY_FULL,
            GameConfig::TUTORIAL_VERSION,
            $firstStep,
        ]);
    }

    public function state(array $user): array
    {
        $userId = (int) $user['id'];
        $progress = $this->findProgress($userId);

        if ($progress === null) {
            $this->createForNewUser($userId);
            $progress = $this->findProgress($userId);
        }

        (new TutorialUpdateService())->ensureCurrentProgress($userId);
        $progress = $this->findProgress($userId);

        if ($progress === null) {
            throw new RuntimeException('Tutorial progress was not found.');
        }

        Database::pdo()->prepare(
            'UPDATE user_tutorial_progress SET last_seen_at = NOW(), updated_at = NOW() WHERE user_id = ?'
        )->execute([$userId]);

        return $this->formatState($progress);
    }

    public function current(array $user): array
    {
        return $this->state($user);
    }

    public function steps(array $user): array
    {
        $state = $this->state($user);

        return [
            'tutorial_key' => $state['tutorial_key'],
            'tutorial_version' => $state['tutorial_version'],
            'modules' => $state['modules'],
            'steps' => $state['steps'],
        ];
    }

    public function recordObjective(array $user, string $actionType, array $payload = []): array
    {
        $actionType = trim($actionType);

        if ($actionType === '') {
            throw new RuntimeException('Objective action type is required.');
        }

        $pageKey = isset($payload['page']) ? (string) $payload['page'] : null;
        $relatedType = isset($payload['related_type']) ? (string) $payload['related_type'] : null;
        $relatedId = isset($payload['related_id']) ? (int) $payload['related_id'] : null;

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO tutorial_objective_events (
                    user_id,
                    action_type,
                    page_key,
                    related_type,
                    related_id,
                    payload,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            (int) $user['id'],
            $actionType,
            $pageKey,
            $relatedType,
            $relatedId,
            json_encode($payload),
        ]);

        return $this->state($user);
    }

    public function advance(array $user, string $stepCode, bool $acknowledged = false): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $progress = $this->lockProgress((int) $user['id']);

            if ($progress['status'] !== 'active') {
                throw new RuntimeException('The tutorial is not active.');
            }

            if ($progress['current_step_code'] !== $stepCode) {
                throw new RuntimeException('Complete the current tutorial step first.');
            }

            $tutorialKey = (string) ($progress['tutorial_key'] ?? GameConfig::TUTORIAL_KEY_FULL);
            $step = $this->findStep($tutorialKey, $stepCode);

            if ($step === null) {
                throw new RuntimeException('Unknown tutorial step.');
            }

            if (!$this->objectiveIsComplete((int) $user['id'], $step, $acknowledged)) {
                throw new RuntimeException('The gameplay objective for this step is not complete.');
            }

            $completedSteps = $this->decodeList($progress['completed_steps']);

            if (!in_array($stepCode, $completedSteps, true)) {
                $completedSteps[] = $stepCode;
            }

            $this->markStep($user, $progress, $stepCode, 'completed');
            $this->logStepEvent((int) $user['id'], $stepCode, 'completed', ['acknowledged' => $acknowledged]);

            $nextStep = $this->nextStepAfter($tutorialKey, $stepCode);
            $isComplete = $nextStep === null;
            $reward = ['cash' => 0, 'xp' => 0];

            if ($isComplete) {
                $rewardPayload = (array) ($step['reward_payload'] ?? []);
                $reward = (new TutorialRewardService())->grantOnce(
                    (int) $user['id'],
                    $tutorialKey,
                    GameConfig::TUTORIAL_VERSION,
                    $rewardPayload
                );

                $this->completeTutorialProgress((int) $user['id'], $tutorialKey, $completedSteps);
            } else {
                Database::pdo()->prepare(
                    <<<'SQL'
                        UPDATE user_tutorial_progress
                        SET
                            current_step_code = ?,
                            completed_steps = ?,
                            updated_at = NOW()
                        WHERE user_id = ?
                    SQL
                )->execute([
                    $nextStep['code'],
                    json_encode($completedSteps),
                    (int) $user['id'],
                ]);
            }

            $pdo->commit();

            $state = $this->state($user);
            $state['reward_granted'] = $reward;

            return $state;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function skip(array $user): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $progress = $this->lockProgress((int) $user['id']);

            if ($progress['status'] === 'completed') {
                throw new RuntimeException('A completed tutorial cannot be skipped.');
            }

            $tutorialKey = (string) ($progress['tutorial_key'] ?? GameConfig::TUTORIAL_KEY_FULL);

            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE user_tutorial_progress
                    SET
                        status = 'skipped',
                        current_step_code = 'skipped',
                        skipped_at = NOW(),
                        updated_at = NOW()
                    WHERE user_id = ?
                SQL
            )->execute([(int) $user['id']]);

            if ($tutorialKey === GameConfig::TUTORIAL_KEY_UPDATE) {
                $dismissed = $this->decodeList($progress['dismissed_update_tutorial_versions'] ?? '[]');

                if (!in_array(GameConfig::TUTORIAL_VERSION, $dismissed, true)) {
                    $dismissed[] = GameConfig::TUTORIAL_VERSION;
                }

                Database::pdo()->prepare(
                    <<<'SQL'
                        UPDATE user_tutorial_progress
                        SET dismissed_update_tutorial_versions = ?, updated_at = NOW()
                        WHERE user_id = ?
                    SQL
                )->execute([json_encode($dismissed), (int) $user['id']]);
            }

            $this->markStep($user, $progress, (string) $progress['current_step_code'], 'skipped');
            $this->logStepEvent((int) $user['id'], (string) $progress['current_step_code'], 'skipped', []);

            $pdo->commit();

            return $this->state($user);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function reopenHelp(array $user): array
    {
        $progress = $this->findProgress((int) $user['id']);

        if ($progress === null) {
            throw new RuntimeException('Tutorial progress was not found.');
        }

        Database::pdo()->prepare(
            'UPDATE user_tutorial_progress SET reopened_at = NOW(), last_seen_at = NOW(), updated_at = NOW() WHERE user_id = ?'
        )->execute([(int) $user['id']]);

        $this->logStepEvent((int) $user['id'], (string) $progress['current_step_code'], 'reopened', []);

        $state = $this->state($user);
        $state['help_mode'] = true;

        return $state;
    }

    public function resetDev(array $user): array
    {
        if (($user['role'] ?? '') !== 'admin') {
            throw new RuntimeException('Only admins can reset tutorial progress.');
        }

        $firstStep = GameConfig::tutorialSteps(GameConfig::TUTORIAL_KEY_FULL)[0]['code'];

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE user_tutorial_progress
                SET
                    tutorial_key = ?,
                    tutorial_version = ?,
                    status = 'active',
                    current_step_code = ?,
                    completed_steps = JSON_ARRAY(),
                    rewards_claimed = JSON_ARRAY(),
                    started_at = NOW(),
                    completed_at = NULL,
                    skipped_at = NULL,
                    reopened_at = NOW(),
                    updated_at = NOW()
                WHERE user_id = ?
            SQL
        )->execute([
            GameConfig::TUTORIAL_KEY_FULL,
            GameConfig::TUTORIAL_VERSION,
            $firstStep,
            (int) $user['id'],
        ]);

        return $this->state($user);
    }

    private function objectiveIsComplete(int $userId, array $step, bool $acknowledged): bool
    {
        return (new TutorialObjectiveValidator())->isComplete($userId, $step, $acknowledged);
    }

    private function completeTutorialProgress(int $userId, string $tutorialKey, array $completedSteps): void
    {
        if ($tutorialKey === GameConfig::TUTORIAL_KEY_UPDATE) {
            $progress = $this->lockProgress($userId);
            $completedUpdates = $this->decodeList($progress['completed_update_tutorial_versions'] ?? '[]');

            if (!in_array(GameConfig::TUTORIAL_VERSION, $completedUpdates, true)) {
                $completedUpdates[] = GameConfig::TUTORIAL_VERSION;
            }

            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE user_tutorial_progress
                    SET
                        status = 'completed',
                        current_step_code = 'completed',
                        completed_steps = ?,
                        completed_update_tutorial_versions = ?,
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE user_id = ?
                SQL
            )->execute([json_encode($completedSteps), json_encode($completedUpdates), $userId]);

            return;
        }

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE user_tutorial_progress
                SET
                    status = 'completed',
                    current_step_code = 'completed',
                    completed_steps = ?,
                    completed_tutorial_version = ?,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE user_id = ?
            SQL
        )->execute([json_encode($completedSteps), GameConfig::TUTORIAL_VERSION, $userId]);
    }

    private function markStep(array $user, array $progress, string $stepCode, string $status): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO user_tutorial_step_progress (
                    user_id,
                    tutorial_key,
                    tutorial_version,
                    step_key,
                    status,
                    completed_at,
                    skipped_at,
                    reward_claimed_at,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END, CASE WHEN ? = 'skipped' THEN NOW() ELSE NULL END, NULL, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    completed_at = CASE WHEN VALUES(status) = 'completed' THEN NOW() ELSE completed_at END,
                    skipped_at = CASE WHEN VALUES(status) = 'skipped' THEN NOW() ELSE skipped_at END,
                    updated_at = NOW()
            SQL
        )->execute([
            (int) $user['id'],
            (string) ($progress['tutorial_key'] ?? GameConfig::TUTORIAL_KEY_FULL),
            (string) ($progress['tutorial_version'] ?? GameConfig::TUTORIAL_VERSION),
            $stepCode,
            $status,
            $status,
            $status,
        ]);
    }

    private function findProgress(int $userId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM user_tutorial_progress WHERE user_id = ? LIMIT 1'
        );
        $statement->execute([$userId]);
        $progress = $statement->fetch();

        return $progress ?: null;
    }

    private function lockProgress(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM user_tutorial_progress WHERE user_id = ? FOR UPDATE'
        );
        $statement->execute([$userId]);
        $progress = $statement->fetch();

        if (!$progress) {
            throw new RuntimeException('Tutorial progress was not found.');
        }

        return $progress;
    }

    private function findStep(string $tutorialKey, string $stepCode): ?array
    {
        foreach (GameConfig::tutorialSteps($tutorialKey) as $step) {
            if ($step['code'] === $stepCode) {
                return $step;
            }
        }

        return null;
    }

    private function nextStepAfter(string $tutorialKey, string $stepCode): ?array
    {
        $steps = GameConfig::tutorialSteps($tutorialKey);

        foreach ($steps as $index => $step) {
            if ($step['code'] !== $stepCode) {
                continue;
            }

            return $steps[$index + 1] ?? null;
        }

        return null;
    }

    private function formatState(array $progress): array
    {
        $tutorialKey = (string) ($progress['tutorial_key'] ?? GameConfig::TUTORIAL_KEY_FULL);
        $version = (string) ($progress['tutorial_version'] ?? GameConfig::TUTORIAL_VERSION);
        $steps = GameConfig::tutorialSteps($tutorialKey);
        $completedSteps = $this->decodeList($progress['completed_steps']);
        $currentStep = $this->findStep($tutorialKey, (string) $progress['current_step_code']);

        foreach ($steps as &$step) {
            $step['completed'] = in_array($step['code'], $completedSteps, true);
            $step['route_hint'] = $step['route_hint'] ?? $step['page'];
        }
        unset($step);

        return [
            'tutorial_key' => $tutorialKey,
            'tutorial_version' => $version,
            'title' => $tutorialKey === GameConfig::TUTORIAL_KEY_UPDATE
                ? 'World Systems Update'
                : 'New Player World Guide',
            'is_update_tutorial' => $tutorialKey === GameConfig::TUTORIAL_KEY_UPDATE,
            'status' => $progress['status'],
            'current_step_code' => $progress['current_step_code'],
            'current_step' => $currentStep,
            'completed_steps' => $completedSteps,
            'steps' => $steps,
            'modules' => $this->moduleProgress($steps, $completedSteps),
            'started_at' => $progress['started_at'] ?? null,
            'completed_at' => $progress['completed_at'] ?? null,
            'skipped_at' => $progress['skipped_at'] ?? null,
            'completed_tutorial_version' => $progress['completed_tutorial_version'] ?? null,
            'completed_update_tutorial_versions' => $this->decodeList($progress['completed_update_tutorial_versions'] ?? '[]'),
            'dismissed_update_tutorial_versions' => $this->decodeList($progress['dismissed_update_tutorial_versions'] ?? '[]'),
            'progress' => [
                'completed' => count($completedSteps),
                'total' => count($steps),
            ],
        ];
    }

    private function moduleProgress(array $steps, array $completedSteps): array
    {
        $modules = GameConfig::tutorialModules();
        $progress = [];

        foreach ($modules as $moduleKey => $module) {
            $moduleSteps = array_values(array_filter(
                $steps,
                static fn (array $step): bool => ($step['module_key'] ?? '') === $moduleKey
            ));

            if ($moduleSteps === []) {
                continue;
            }

            $completed = array_values(array_filter(
                $moduleSteps,
                static fn (array $step): bool => in_array($step['code'], $completedSteps, true)
            ));

            $progress[] = [
                'module_key' => $moduleKey,
                'title' => $module['title'],
                'description' => $module['description'],
                'completed' => count($completed),
                'total' => count($moduleSteps),
            ];
        }

        return $progress;
    }

    private function decodeList(mixed $json): array
    {
        if (is_array($json)) {
            return array_values($json);
        }

        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function logStepEvent(int $userId, string $stepCode, string $eventType, array $details): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT IGNORE INTO tutorial_step_logs (
                    user_id,
                    step_code,
                    event_type,
                    details,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            SQL
        )->execute([$userId, $stepCode, $eventType, json_encode($details)]);
    }
}
