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
        $sql = <<<'SQL'
            INSERT INTO user_tutorial_progress (
                user_id,
                status,
                current_step_code,
                completed_steps,
                rewards_claimed,
                started_at,
                updated_at
            ) VALUES (?, 'active', 'welcome', JSON_ARRAY(), JSON_ARRAY(), NOW(), NOW())
        SQL;

        Database::pdo()->prepare($sql)->execute([$userId]);
    }

    public function state(array $user): array
    {
        $progress = $this->findProgress((int) $user['id']);

        if ($progress === null) {
            $this->createCompletedFallback((int) $user['id']);
            $progress = $this->findProgress((int) $user['id']);
        }

        return $this->formatState($progress);
    }

    public function advance(
        array $user,
        string $stepCode,
        bool $acknowledged = false
    ): array {
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

            $step = $this->findStep($stepCode);

            if ($step === null) {
                throw new RuntimeException('Unknown tutorial step.');
            }

            if (($step['requires_acknowledgement'] ?? false) && !$acknowledged) {
                throw new RuntimeException('Confirm that you reviewed this tutorial step.');
            }

            if (!$this->gameplayRequirementIsMet((int) $user['id'], $stepCode)) {
                throw new RuntimeException('The gameplay objective for this step is not complete.');
            }

            $completedSteps = $this->decodeList($progress['completed_steps']);

            if (!in_array($stepCode, $completedSteps, true)) {
                $completedSteps[] = $stepCode;
            }

            $nextStep = $this->nextStepAfter($stepCode);
            $isComplete = $nextStep === null;
            $nextStepCode = $isComplete ? 'completed' : $nextStep['code'];
            $status = $isComplete ? 'completed' : 'active';

            $update = $pdo->prepare(
                <<<'SQL'
                    UPDATE user_tutorial_progress
                    SET
                        status = ?,
                        current_step_code = ?,
                        completed_steps = ?,
                        completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END,
                        updated_at = NOW()
                    WHERE user_id = ?
                SQL
            );

            $update->execute([
                $status,
                $nextStepCode,
                json_encode($completedSteps),
                $status,
                $user['id'],
            ]);

            $this->logEvent(
                (int) $user['id'],
                $stepCode,
                'completed',
                ['acknowledged' => $acknowledged]
            );

            $reward = 0;

            if ($isComplete) {
                $reward = $this->grantCompletionReward(
                    (int) $user['id'],
                    $progress
                );
            }

            $pdo->commit();

            $newState = $this->state($user);
            $newState['reward_granted'] = $reward;

            return $newState;
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

            if ($progress['status'] !== 'skipped') {
                $statement = $pdo->prepare(
                    <<<'SQL'
                        UPDATE user_tutorial_progress
                        SET
                            status = 'skipped',
                            current_step_code = 'skipped',
                            skipped_at = NOW(),
                            updated_at = NOW()
                        WHERE user_id = ?
                    SQL
                );

                $statement->execute([$user['id']]);

                $this->logEvent(
                    (int) $user['id'],
                    (string) $progress['current_step_code'],
                    'skipped',
                    []
                );
            }

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

        $this->logEvent(
            (int) $user['id'],
            (string) $progress['current_step_code'],
            'reopened',
            []
        );

        $state = $this->formatState($progress);
        $state['help_mode'] = true;

        return $state;
    }

    private function gameplayRequirementIsMet(int $userId, string $stepCode): bool
    {
        return match ($stepCode) {
            'welcome',
            'crew_overview',
            'heat_consequences',
            'warehouse_intro' => true,
            'first_money' => $this->hasCompletedLegalJob($userId),
            'first_illegal_job' => $this->hasAttemptedIllegalWork($userId),
            'first_recruit' => $this->hasCrewMember($userId),
            'basic_equipment' => $this->hasEquippedCrewMember($userId),
            'prepare_dirty_job' => $this->hasPreparedDirtyJob($userId),
            'execute_dirty_job' => $this->hasResolvedDirtyJob($userId),
            default => false,
        };
    }

    private function hasCompletedLegalJob(int $userId): bool
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM job_runs run
            JOIN job_opportunities opportunity
                ON opportunity.id = run.opportunity_id
            JOIN jobs job
                ON job.id = opportunity.job_id
            WHERE run.user_id = ?
              AND run.status = 'completed'
              AND job.category = 'legal'
        SQL;

        return $this->count($sql, [$userId]) > 0;
    }

    private function hasAttemptedIllegalWork(int $userId): bool
    {
        $jobSql = <<<'SQL'
            SELECT COUNT(*)
            FROM job_runs run
            JOIN job_opportunities opportunity
                ON opportunity.id = run.opportunity_id
            JOIN jobs job
                ON job.id = opportunity.job_id
            WHERE run.user_id = ?
              AND run.status IN ('completed', 'failed')
              AND job.category = 'criminal'
        SQL;

        if ($this->count($jobSql, [$userId]) > 0) {
            return true;
        }

        return $this->count(
            'SELECT COUNT(*) FROM crime_logs WHERE user_id = ?',
            [$userId]
        ) > 0;
    }

    private function hasCrewMember(int $userId): bool
    {
        return $this->count(
            <<<'SQL'
                SELECT COUNT(*)
                FROM player_gang_members
                WHERE user_id = ?
                  AND status <> 'dismissed'
            SQL,
            [$userId]
        ) > 0;
    }

    private function hasEquippedCrewMember(int $userId): bool
    {
        return $this->count(
            'SELECT COUNT(*) FROM crew_equipment WHERE user_id = ?',
            [$userId]
        ) > 0;
    }

    private function hasPreparedDirtyJob(int $userId): bool
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM dirty_job_preparations preparation
            JOIN dirty_job_runs run
                ON run.id = preparation.dirty_job_run_id
            WHERE run.user_id = ?
        SQL;

        return $this->count($sql, [$userId]) > 0;
    }

    private function hasResolvedDirtyJob(int $userId): bool
    {
        return $this->count(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_runs
                WHERE user_id = ?
                  AND status IN (
                    'completed',
                    'partially_completed',
                    'failed'
                  )
            SQL,
            [$userId]
        ) > 0;
    }

    private function grantCompletionReward(int $userId, array $progress): int
    {
        $rewardsClaimed = $this->decodeList($progress['rewards_claimed']);
        $rewardCode = 'tutorial_completion_cash';

        if (in_array($rewardCode, $rewardsClaimed, true)) {
            return 0;
        }

        $reward = GameConfig::TUTORIAL_COMPLETION_REWARD;
        $rewardsClaimed[] = $rewardCode;

        Database::pdo()->prepare(
            'UPDATE users SET cash = cash + ?, updated_at = NOW() WHERE id = ?'
        )->execute([$reward, $userId]);

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE user_tutorial_progress
                SET rewards_claimed = ?, updated_at = NOW()
                WHERE user_id = ?
            SQL
        )->execute([json_encode($rewardsClaimed), $userId]);

        (new EconomyLedgerService())->record(
            'tutorial_reward',
            $reward,
            'One-time tutorial completion reward',
            [
                'source_type' => 'tutorial',
                'destination_type' => 'player',
                'destination_id' => $userId,
                'user_id' => $userId,
            ]
        );

        $this->logEvent(
            $userId,
            'warehouse_intro',
            'reward',
            ['cash' => $reward]
        );

        return $reward;
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

    private function createCompletedFallback(int $userId): void
    {
        $stepCodes = array_column(GameConfig::tutorialSteps(), 'code');

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO user_tutorial_progress (
                    user_id,
                    status,
                    current_step_code,
                    completed_steps,
                    rewards_claimed,
                    started_at,
                    completed_at,
                    updated_at
                ) VALUES (?, 'completed', 'completed', ?, JSON_ARRAY(), NOW(), NOW(), NOW())
            SQL
        );

        $statement->execute([$userId, json_encode($stepCodes)]);
    }

    private function findStep(string $stepCode): ?array
    {
        foreach (GameConfig::tutorialSteps() as $step) {
            if ($step['code'] === $stepCode) {
                return $step;
            }
        }

        return null;
    }

    private function nextStepAfter(string $stepCode): ?array
    {
        $steps = GameConfig::tutorialSteps();

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
        $steps = GameConfig::tutorialSteps();
        $completedSteps = $this->decodeList($progress['completed_steps']);
        $currentStep = $this->findStep((string) $progress['current_step_code']);

        foreach ($steps as &$step) {
            $step['completed'] = in_array(
                $step['code'],
                $completedSteps,
                true
            );
        }

        return [
            'status' => $progress['status'],
            'current_step_code' => $progress['current_step_code'],
            'current_step' => $currentStep,
            'completed_steps' => $completedSteps,
            'steps' => $steps,
            'started_at' => $progress['started_at'],
            'completed_at' => $progress['completed_at'],
            'skipped_at' => $progress['skipped_at'],
            'progress' => [
                'completed' => count($completedSteps),
                'total' => count($steps),
            ],
        ];
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

    private function count(string $sql, array $parameters): int
    {
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function logEvent(
        int $userId,
        string $stepCode,
        string $eventType,
        array $details
    ): void {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT IGNORE INTO tutorial_step_logs (
                    user_id,
                    step_code,
                    event_type,
                    details,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            SQL
        );

        $statement->execute([
            $userId,
            $stepCode,
            $eventType,
            json_encode($details),
        ]);
    }
}
