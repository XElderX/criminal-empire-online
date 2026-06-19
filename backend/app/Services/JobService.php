<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class JobService
{
    public function refreshOpportunityList(): array
    {
        $pdo = Database::pdo();

        $expired = $pdo->exec(
            <<<'SQL'
                UPDATE job_opportunities
                SET status = 'expired'
                WHERE status = 'available'
                  AND expires_at IS NOT NULL
                  AND expires_at <= NOW()
            SQL
        );

        $refreshed = $pdo->exec(
            <<<'SQL'
                UPDATE job_opportunities opportunity
                JOIN jobs job ON job.id = opportunity.job_id
                SET
                    opportunity.status = 'available',
                    opportunity.available_from = NOW(),
                    opportunity.expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY)
                WHERE job.active = 1
                  AND opportunity.status IN ('completed', 'expired')
            SQL
        );

        $this->restoreLegalStarterJobsIfNeeded();

        $available = (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM job_opportunities opportunity
                JOIN jobs job ON job.id = opportunity.job_id
                WHERE opportunity.status = 'available'
                  AND job.active = 1
                  AND opportunity.available_from <= NOW()
                  AND (
                    opportunity.expires_at IS NULL
                    OR opportunity.expires_at > NOW()
                  )
            SQL
        )->fetchColumn();

        return [
            'expired' => (int) $expired,
            'refreshed' => (int) $refreshed,
            'available' => $available,
        ];
    }

    public function listForUser(array $user): array
    {
        $this->restoreLegalStarterJobsIfNeeded();

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    opportunity.id AS opportunity_id,
                    job.*,
                    territory.name AS territory_name,
                    giver.first_name AS giver_first_name,
                    giver.last_name AS giver_last_name,
                    giver.nickname AS giver_nickname
                FROM job_opportunities opportunity
                JOIN jobs job ON job.id = opportunity.job_id
                JOIN territories territory ON territory.id = opportunity.territory_id
                LEFT JOIN npcs giver ON giver.id = opportunity.giver_npc_id
                WHERE opportunity.status = 'available'
                  AND job.active = 1
                  AND opportunity.available_from <= NOW()
                  AND (
                    opportunity.expires_at IS NULL
                    OR opportunity.expires_at > NOW()
                  )
                ORDER BY job.reward_min, job.id
            SQL
        );
        $statement->execute();
        $jobs = $statement->fetchAll();

        foreach ($jobs as &$job) {
            $job['duration_seconds_effective'] = max(
                1,
                (int) round(
                    (int) $job['duration_seconds']
                    * GameConfig::jobDurationMultiplier()
                )
            );

            $job['requirement_messages'] = $this->requirementMessages(
                $user,
                $job
            );
            $job['can_start'] = $job['requirement_messages'] === [];
        }

        return $jobs;
    }

    private function restoreLegalStarterJobsIfNeeded(): void
    {
        $pdo = Database::pdo();

        $activeLegalRuns = (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM job_runs run
                JOIN job_opportunities opportunity
                    ON opportunity.id = run.opportunity_id
                JOIN jobs job
                    ON job.id = opportunity.job_id
                WHERE run.status = 'active'
                  AND job.category = 'legal'
            SQL
        )->fetchColumn();

        if ($activeLegalRuns > 0) {
            return;
        }

        $availableLegalJobs = (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM job_opportunities opportunity
                JOIN jobs job
                    ON job.id = opportunity.job_id
                WHERE opportunity.status = 'available'
                  AND opportunity.available_from <= NOW()
                  AND (opportunity.expires_at IS NULL OR opportunity.expires_at > NOW())
                  AND job.category = 'legal'
            SQL
        )->fetchColumn();

        if ($availableLegalJobs > 0) {
            return;
        }

        $pdo->exec(
            <<<'SQL'
                UPDATE job_opportunities opportunity
                JOIN jobs job ON job.id = opportunity.job_id
                SET
                    opportunity.status = 'available',
                    opportunity.available_from = NOW(),
                    opportunity.expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY)
                WHERE job.category = 'legal'
                  AND job.active = 1
            SQL
        );
    }

    public function active(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    run.*,
                    job.name AS title,
                    job.description,
                    job.reward_min,
                    job.reward_max,
                    territory.name AS territory_name,
                    TIMESTAMPDIFF(SECOND, NOW(), run.completes_at) AS seconds_remaining
                FROM job_runs run
                JOIN job_opportunities opportunity
                    ON opportunity.id = run.opportunity_id
                JOIN jobs job ON job.id = opportunity.job_id
                JOIN territories territory
                    ON territory.id = opportunity.territory_id
                WHERE run.user_id = ?
                  AND run.status = 'active'
                ORDER BY run.id DESC
            SQL
        );
        $statement->execute([$user['id']]);

        return $statement->fetchAll();
    }

    public function start(
        array $user,
        int $opportunityId,
        array $memberIds,
        string $idempotencyKey
    ): array {
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $existingRun = $this->findByIdempotencyKey(
                (int) $user['id'],
                $idempotencyKey
            );

            if ($existingRun) {
                $pdo->commit();

                return [
                    'message' => 'This starter job was already started.',
                    'job_run_id' => (int) $existingRun['id'],
                    'duration_seconds' => max(
                        0,
                        strtotime($existingRun['completes_at']) - time()
                    ),
                ];
            }

            $job = $this->lockOpportunity($opportunityId);

            if (!$job || $job['status'] !== 'available') {
                throw new RuntimeException('Job opportunity is not available.');
            }

            if (
                $job['expires_at'] !== null
                && strtotime($job['expires_at']) <= time()
            ) {
                throw new RuntimeException('Job opportunity has expired.');
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $this->validatePlayerRequirements($freshUser, $job);

            $memberIds = array_values(array_unique(array_map(
                static fn (mixed $memberId): int => (int) $memberId,
                $memberIds
            )));

            if (count($memberIds) < (int) $job['min_gang_size']) {
                throw new RuntimeException(
                    'This job requires more active gang members.'
                );
            }

            foreach ($memberIds as $memberId) {
                $this->validateAndLockMember(
                    $memberId,
                    (int) $freshUser['id']
                );
            }

            $duration = max(
                1,
                (int) round(
                    (int) $job['duration_seconds']
                    * GameConfig::jobDurationMultiplier()
                )
            );

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET energy = energy - ?, updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $job['energy_cost'],
                $freshUser['id'],
            ]);

            $insertRun = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO job_runs (
                        user_id,
                        opportunity_id,
                        idempotency_key,
                        status,
                        started_at,
                        completes_at,
                        created_at,
                        updated_at
                    ) VALUES (
                        ?, ?, ?, 'active', NOW(),
                        DATE_ADD(NOW(), INTERVAL ? SECOND),
                        NOW(), NOW()
                    )
                SQL
            );
            $insertRun->execute([
                $freshUser['id'],
                $opportunityId,
                $idempotencyKey,
                $duration,
            ]);

            $runId = (int) $pdo->lastInsertId();
            $this->assignMembers($runId, $memberIds);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE job_opportunities
                    SET status = 'active'
                    WHERE id = ?
                SQL
            )->execute([$opportunityId]);

            AuditService::log(
                (int) $freshUser['id'],
                'job.start',
                [
                    'job_run_id' => $runId,
                    'opportunity_id' => $opportunityId,
                    'member_ids' => $memberIds,
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Job started.',
                'job_run_id' => $runId,
                'duration_seconds' => $duration,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function complete(array $user, int $runId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRun($runId, (int) $user['id']);

            if (!$run) {
                throw new RuntimeException('Job run not found.');
            }

            if ($run['status'] !== 'active') {
                throw new RuntimeException('Job has already been resolved.');
            }

            if ((int) ($run['seconds_remaining'] ?? 0) > 0) {
                throw new RuntimeException('Job is not complete yet.');
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $members = $this->assignedMembers($runId);
            $district = $this->district((int) $run['territory_id']);
            $successChance = $this->successChance(
                $freshUser,
                $run,
                $members,
                $district
            );

            $success = random_int(1, 100) <= $successChance;
            $reward = $success
                ? random_int((int) $run['reward_min'], (int) $run['reward_max'])
                : 0;
            $heat = $success
                ? random_int((int) $run['heat_min'], (int) $run['heat_max'])
                : max((int) $run['heat_max'], 1);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET
                        cash = cash + ?,
                        heat = heat + ?,
                        experience = experience + ?,
                        reputation = reputation + ?,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $reward,
                $heat,
                $run['experience_gain'],
                $success ? $run['reputation_gain'] : 0,
                $freshUser['id'],
            ]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE job_runs
                    SET
                        status = ?,
                        success = ?,
                        reward = ?,
                        heat_gained = ?,
                        completed_at = NOW(),
                        result = ?,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $success ? 'completed' : 'failed',
                $success ? 1 : 0,
                $reward,
                $heat,
                json_encode(['success_chance' => $successChance]),
                $runId,
            ]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE job_opportunities
                    SET status = 'completed'
                    WHERE id = ?
                SQL
            )->execute([$run['opportunity_id']]);

            $this->releaseMembers(
                $members,
                (int) $run['experience_gain']
            );

            if ($reward > 0) {
                (new EconomyLedgerService())->record(
                    'job_reward',
                    $reward,
                    'NPC or external contract paid a completed job.',
                    [
                        'source_type' => $run['giver_npc_id']
                            ? 'npc'
                            : 'external_contract',
                        'source_id' => $run['giver_npc_id'],
                        'destination_type' => 'player',
                        'destination_id' => $freshUser['id'],
                        'user_id' => $freshUser['id'],
                        'npc_id' => $run['giver_npc_id'],
                        'job_run_id' => $runId,
                        'territory_id' => $run['territory_id'],
                    ]
                );
            }

            AuditService::log(
                (int) $freshUser['id'],
                'job.complete',
                [
                    'job_run_id' => $runId,
                    'success' => $success,
                    'reward' => $reward,
                    'heat' => $heat,
                ]
            );

            $pdo->commit();

            return [
                'success' => $success,
                'reward' => $reward,
                'heat_gained' => $heat,
                'success_chance' => $successChance,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function requirementMessages(array $user, array $job): array
    {
        $messages = [];

        if ((int) $user['energy'] < (int) $job['energy_cost']) {
            $messages[] = 'Not enough energy.';
        }

        if ((int) ($user['reputation'] ?? 0) < (int) $job['min_reputation']) {
            $messages[] = "Requires {$job['min_reputation']} reputation.";
        }

        return $messages;
    }

    private function validatePlayerRequirements(array $user, array $job): void
    {
        $messages = $this->requirementMessages($user, $job);

        if ($messages !== []) {
            throw new RuntimeException(implode(' ', $messages));
        }
    }

    private function normalizeIdempotencyKey(string $idempotencyKey): string
    {
        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '') {
            return bin2hex(random_bytes(16));
        }

        if (strlen($idempotencyKey) > 36) {
            throw new RuntimeException('Idempotency key is too long.');
        }

        return $idempotencyKey;
    }

    private function findByIdempotencyKey(
        int $userId,
        string $idempotencyKey
    ): ?array {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM job_runs
                WHERE user_id = ?
                  AND idempotency_key = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$userId, $idempotencyKey]);
        $run = $statement->fetch();

        return $run ?: null;
    }

    private function lockOpportunity(int $opportunityId): ?array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT opportunity.*, job.*,
                    opportunity.id AS opportunity_id,
                    opportunity.status AS opportunity_status
                FROM job_opportunities opportunity
                JOIN jobs job ON job.id = opportunity.job_id
                WHERE opportunity.id = ?
                FOR UPDATE
            SQL
        );
        $statement->execute([$opportunityId]);
        $job = $statement->fetch();

        if ($job) {
            $job['status'] = $job['opportunity_status'];
        }

        return $job ?: null;
    }

    private function lockUser(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM users WHERE id = ? FOR UPDATE'
        );
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user) {
            throw new RuntimeException('Player not found.');
        }

        return $user;
    }

    private function validateAndLockMember(int $memberId, int $userId): void
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM player_gang_members
                WHERE id = ?
                  AND user_id = ?
                FOR UPDATE
            SQL
        );
        $statement->execute([$memberId, $userId]);
        $member = $statement->fetch();

        if (!$member || $member['status'] !== 'active') {
            throw new RuntimeException('Selected gang member is unavailable.');
        }
    }

    private function assignMembers(int $runId, array $memberIds): void
    {
        $insertAssignment = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO job_assignments (job_run_id, gang_member_id)
                VALUES (?, ?)
            SQL
        );
        $updateMember = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE player_gang_members
                SET
                    status = 'busy',
                    current_assignment_type = 'job',
                    current_assignment_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            SQL
        );

        foreach ($memberIds as $memberId) {
            $insertAssignment->execute([$runId, $memberId]);
            $updateMember->execute([$runId, $memberId]);
        }
    }

    private function lockRun(int $runId, int $userId): ?array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    run.*,
                    TIMESTAMPDIFF(SECOND, NOW(), run.completes_at) AS seconds_remaining,
                    opportunity.job_id,
                    opportunity.territory_id,
                    opportunity.giver_npc_id,
                    opportunity.source_budget,
                    job.name,
                    job.category,
                    job.reward_min,
                    job.reward_max,
                    job.heat_min,
                    job.heat_max,
                    job.experience_gain,
                    job.reputation_gain,
                    job.base_success_rate,
                    job.difficulty,
                    job.required_stat,
                    job.required_stat_value
                FROM job_runs run
                JOIN job_opportunities opportunity
                    ON opportunity.id = run.opportunity_id
                JOIN jobs job ON job.id = opportunity.job_id
                WHERE run.id = ?
                  AND run.user_id = ?
                FOR UPDATE
            SQL
        );
        $statement->execute([$runId, $userId]);
        $run = $statement->fetch();

        return $run ?: null;
    }

    private function assignedMembers(int $runId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT member.*
                FROM job_assignments assignment
                JOIN player_gang_members member
                    ON member.id = assignment.gang_member_id
                WHERE assignment.job_run_id = ?
            SQL
        );
        $statement->execute([$runId]);

        return $statement->fetchAll();
    }

    private function district(int $territoryId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM territories WHERE id = ?'
        );
        $statement->execute([$territoryId]);

        return $statement->fetch() ?: [];
    }

    private function successChance(
        array $user,
        array $run,
        array $members,
        array $district
    ): int {
        if ($run['category'] === 'legal') {
            return 95;
        }

        $statBonus = (int) $user['intelligence'];
        $requiredStat = $run['required_stat'] ?: 'discipline';

        foreach ($members as $member) {
            $statBonus += (int) ($member[$requiredStat] ?? 0) / 5;
        }

        $policePenalty = (int) round(
            (int) ($district['police_presence'] ?? 50) / 10
        );
        $difficultyPenalty = (int) $run['difficulty'] * 3;

        $chance = (int) $run['base_success_rate']
            + $statBonus
            - $difficultyPenalty
            - $policePenalty;

        return max(5, min(95, $chance));
    }

    private function releaseMembers(array $members, int $experienceGain): void
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE player_gang_members
                SET
                    status = 'active',
                    current_assignment_type = NULL,
                    current_assignment_id = NULL,
                    experience = experience + ?,
                    updated_at = NOW()
                WHERE id = ?
            SQL
        );

        foreach ($members as $member) {
            $statement->execute([$experienceGain, $member['id']]);
        }
    }
}
