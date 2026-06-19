<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class DirtyJobService
{
    private DirtyJobCalculator $calculator;
    private ExperienceService $experience;

    public function __construct(
        private readonly RandomSource $random = new SecureRandomSource()
    ) {
        $this->calculator = new DirtyJobCalculator($this->random);
        $this->experience = new ExperienceService();
    }

    public function opportunities(array $user): array
    {
        (new DirtyJobGeneratorService())->ensureForUser($user);

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    opportunity.*,
                    template.code,
                    template.category,
                    template.tier,
                    template.title,
                    template.short_description,
                    template.introduction,
                    template.reward_min,
                    template.reward_max,
                    template.energy_cost,
                    template.heat_min,
                    template.heat_max,
                    template.min_level,
                    template.min_reputation,
                    template.min_crew_size,
                    template.required_roles,
                    template.required_items,
                    template.requires_warehouse,
                    territory.name AS territory_name,
                    territory.police_presence,
                    territory.wealth,
                    npc.first_name AS contact_first_name,
                    npc.last_name AS contact_last_name,
                    npc.nickname AS contact_nickname,
                    contact.contact_type,
                    relationship.trust AS contact_trust
                FROM dirty_job_opportunities opportunity
                JOIN dirty_job_templates template
                    ON template.id = opportunity.template_id
                JOIN territories territory
                    ON territory.id = opportunity.territory_id
                LEFT JOIN npc_contacts contact
                    ON contact.id = opportunity.contact_id
                LEFT JOIN npcs npc
                    ON npc.id = contact.npc_id
                LEFT JOIN contact_relationships relationship
                    ON relationship.contact_id = contact.id
                    AND relationship.user_id = opportunity.user_id
                WHERE opportunity.user_id = ?
                  AND opportunity.status = 'available'
                  AND opportunity.available_from <= NOW()
                  AND opportunity.expires_at > NOW()
                ORDER BY template.tier, opportunity.expires_at, opportunity.id
            SQL
        );
        $statement->execute([$user['id']]);
        $opportunities = $statement->fetchAll();

        $crewCount = $this->availableCrewCount((int) $user['id']);
        $hasWarehouse = (new WarehouseService())->firstWarehouseForUser(
            (int) $user['id']
        ) !== null;

        foreach ($opportunities as &$opportunity) {
            $opportunity['required_roles'] = $this->decodeJson(
                $opportunity['required_roles']
            );
            $opportunity['required_items'] = $this->decodeJson(
                $opportunity['required_items']
            );
            $opportunity['narrative_variables'] = $this->decodeJson(
                $opportunity['narrative_variables']
            );
            $opportunity['contact_name'] = $this->contactName($opportunity);
            $opportunity['estimated_reward_min'] = (int) round(
                (int) $opportunity['reward_min']
                * (float) $opportunity['reward_multiplier']
            );
            $opportunity['estimated_reward_max'] = (int) round(
                (int) $opportunity['reward_max']
                * (float) $opportunity['reward_multiplier']
            );
            $opportunity['can_accept'] = (int) $user['level'] >= (int) $opportunity['min_level']
                && (int) $user['reputation'] >= (int) $opportunity['min_reputation']
                && $crewCount >= (int) $opportunity['min_crew_size']
                && (!(bool) $opportunity['requires_warehouse'] || $hasWarehouse);
            $opportunity['requirement_messages'] = $this->requirementMessages(
                $user,
                $opportunity,
                $crewCount,
                $hasWarehouse
            );
        }

        return $opportunities;
    }

    public function detail(array $user, int $opportunityId): array
    {
        $crewCount = $this->availableCrewCount((int) $user['id']);
        $hasWarehouse = (new WarehouseService())->firstWarehouseForUser(
            (int) $user['id']
        ) !== null;

        $opportunity = $this->loadOpportunity(
            (int) $user['id'],
            $opportunityId
        );

        if (!$opportunity) {
            throw new RuntimeException('Dirty Job opportunity not found.');
        }

        $runStatement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM dirty_job_runs
                WHERE user_id = ?
                  AND opportunity_id = ?
                LIMIT 1
            SQL
        );
        $runStatement->execute([$user['id'], $opportunityId]);
        $run = $runStatement->fetch();

        return [
            'opportunity' => $this->formatOpportunity($opportunity) + [
                'can_accept' => (int) $user['level'] >= (int) $opportunity['min_level']
                    && (int) $user['reputation'] >= (int) $opportunity['min_reputation']
                    && $crewCount >= (int) $opportunity['min_crew_size']
                    && (!(bool) $opportunity['requires_warehouse'] || $hasWarehouse),
                'requirement_messages' => $this->requirementMessages(
                    $user,
                    $opportunity,
                    $crewCount,
                    $hasWarehouse
                ),
            ],
            'run' => $run ? $this->hydrateRun($run) : null,
            'crew_roles' => GameConfig::crewRoleDefinitions(),
        ];
    }

    public function accept(
        array $user,
        int $opportunityId,
        string $idempotencyKey
    ): array {
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $existingStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT *
                    FROM dirty_job_runs
                    WHERE user_id = ?
                      AND idempotency_key = ?
                    LIMIT 1
                SQL
            );
            $existingStatement->execute([$user['id'], $idempotencyKey]);
            $existingRun = $existingStatement->fetch();

            if ($existingRun) {
                $pdo->commit();

                return [
                    'message' => 'Dirty Job was already accepted.',
                    'run' => $this->hydrateRun($existingRun),
                ];
            }

            $opportunity = $this->lockOpportunity(
                (int) $user['id'],
                $opportunityId
            );

            if ($opportunity['status'] !== 'available') {
                throw new RuntimeException('Dirty Job opportunity is unavailable.');
            }

            if (strtotime($opportunity['expires_at']) <= time()) {
                throw new RuntimeException('Dirty Job opportunity has expired.');
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $this->validateUnlockRequirements($freshUser, $opportunity);

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO dirty_job_runs (
                        user_id,
                        opportunity_id,
                        idempotency_key,
                        status,
                        accepted_at,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, 'accepted', NOW(), NOW(), NOW())
                SQL
            );
            $insert->execute([
                $freshUser['id'],
                $opportunityId,
                $idempotencyKey,
            ]);

            $runId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                <<<'SQL'
                    UPDATE dirty_job_opportunities
                    SET
                        status = 'accepted',
                        accepted_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$opportunityId]);

            AuditService::log(
                (int) $freshUser['id'],
                'dirty_job.accept',
                [
                    'opportunity_id' => $opportunityId,
                    'run_id' => $runId,
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Dirty Job accepted.',
                'dirty_job_run_id' => $runId,
                'run' => $this->hydrateRun(
                    $this->findRun($runId, (int) $freshUser['id'])
                ),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function prepare(
        array $user,
        int $runId,
        string $actionCode
    ): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRunWithTemplate($runId, (int) $user['id']);

            if (!in_array($run['status'], ['accepted', 'preparing', 'ready'], true)) {
                throw new RuntimeException('Preparation is closed for this Dirty Job.');
            }

            $options = $this->decodeJson($run['preparation_options']);
            $selectedOption = null;

            foreach ($options as $option) {
                if (($option['code'] ?? null) === $actionCode) {
                    $selectedOption = $option;
                    break;
                }
            }

            if ($selectedOption === null) {
                throw new RuntimeException('Unknown preparation action.');
            }

            $duplicateStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT COUNT(*)
                    FROM dirty_job_preparations
                    WHERE dirty_job_run_id = ?
                      AND action_code = ?
                SQL
            );
            $duplicateStatement->execute([$runId, $actionCode]);

            if ((int) $duplicateStatement->fetchColumn() > 0) {
                throw new RuntimeException('This preparation action is already complete.');
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $cashCost = (int) ($selectedOption['cash_cost'] ?? 0);
            $energyCost = (int) ($selectedOption['energy_cost'] ?? 0);

            if ((int) $freshUser['cash'] < $cashCost) {
                throw new RuntimeException('Not enough cash for this preparation.');
            }

            if ((int) $freshUser['energy'] < $energyCost) {
                throw new RuntimeException('Not enough energy for this preparation.');
            }

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET
                        cash = cash - ?,
                        energy = energy - ?,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$cashCost, $energyCost, $freshUser['id']]);

            $statement = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO dirty_job_preparations (
                        dirty_job_run_id,
                        action_code,
                        cash_cost,
                        energy_cost,
                        success_bonus,
                        heat_modifier,
                        injury_modifier,
                        reward_modifier,
                        details,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                SQL
            );

            $statement->execute([
                $runId,
                $actionCode,
                $cashCost,
                $energyCost,
                (int) ($selectedOption['success_bonus'] ?? 0),
                (int) ($selectedOption['heat_modifier'] ?? 0),
                (int) ($selectedOption['injury_modifier'] ?? 0),
                (float) ($selectedOption['reward_modifier'] ?? 1.0),
                json_encode($selectedOption),
            ]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE dirty_job_runs
                    SET status = 'preparing', updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$runId]);

            if ($cashCost > 0) {
                (new EconomyLedgerService())->record(
                    'dirty_job_preparation',
                    $cashCost,
                    "Dirty Job preparation: {$selectedOption['name']}",
                    [
                        'source_type' => 'player',
                        'source_id' => $freshUser['id'],
                        'destination_type' => 'npc_contact',
                        'user_id' => $freshUser['id'],
                        'territory_id' => $run['territory_id'],
                    ]
                );
            }

            $pdo->commit();

            return [
                'message' => 'Preparation action completed.',
                'run' => $this->hydrateRun(
                    $this->findRun($runId, (int) $freshUser['id'])
                ),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function assignCrew(
        array $user,
        int $runId,
        array $assignments
    ): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRunWithTemplate($runId, (int) $user['id']);

            if (!in_array($run['status'], ['accepted', 'preparing', 'ready'], true)) {
                throw new RuntimeException('Crew assignments can no longer be changed.');
            }

            $normalized = $this->normalizeAssignments($assignments);
            $seenMembers = [];
            $seenRoles = [];

            foreach ($normalized as $assignment) {
                if (isset($seenMembers[$assignment['member_id']])) {
                    throw new RuntimeException('A member cannot fill two roles in one job.');
                }

                if (isset($seenRoles[$assignment['role_code']])) {
                    throw new RuntimeException('Each operation role can only be assigned once.');
                }

                $this->validateCrewMemberForAssignment(
                    (int) $user['id'],
                    $runId,
                    $assignment['member_id']
                );

                $seenMembers[$assignment['member_id']] = true;
                $seenRoles[$assignment['role_code']] = true;
            }

            $pdo->prepare(
                'DELETE FROM dirty_job_assignments WHERE dirty_job_run_id = ?'
            )->execute([$runId]);

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO dirty_job_assignments (
                        dirty_job_run_id,
                        gang_member_id,
                        role_code,
                        created_at
                    ) VALUES (?, ?, ?, NOW())
                SQL
            );

            foreach ($normalized as $assignment) {
                $insert->execute([
                    $runId,
                    $assignment['member_id'],
                    $assignment['role_code'],
                ]);
            }

            $pdo->prepare(
                <<<'SQL'
                    UPDATE dirty_job_runs
                    SET status = 'preparing', updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$runId]);

            $pdo->commit();

            return [
                'message' => 'Crew assignments saved.',
                'assignments' => $this->loadAssignments($runId),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function startExecution(array $user, int $runId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRunWithTemplate($runId, (int) $user['id']);

            if (!in_array($run['status'], ['accepted', 'preparing', 'ready'], true)) {
                throw new RuntimeException('This Dirty Job cannot begin execution.');
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $assignments = $this->loadAssignments($runId, true);
            $this->validateExecutionRequirements($freshUser, $run, $assignments);

            if ((int) $freshUser['energy'] < (int) $run['energy_cost']) {
                throw new RuntimeException('Not enough energy to execute this Dirty Job.');
            }

            $duration = max(
                1,
                (int) round(
                    (int) $run['duration_seconds']
                    * GameConfig::jobDurationMultiplier()
                )
            );

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET energy = energy - ?, updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$run['energy_cost'], $freshUser['id']]);

            $memberIds = array_column($assignments, 'gang_member_id');
            $equipment = (new EquipmentEffectService())->loadForMembers($memberIds);
            $equipmentInsert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO dirty_job_equipment (
                        dirty_job_run_id,
                        crew_equipment_id,
                        gang_member_id,
                        effects_snapshot,
                        durability_before,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                SQL
            );

            foreach ($equipment as $entry) {
                $equipmentInsert->execute([
                    $runId,
                    $entry['crew_equipment_id'],
                    $entry['gang_member_id'],
                    json_encode($entry['effects']),
                    $entry['durability'],
                ]);
            }

            $memberUpdate = $pdo->prepare(
                <<<'SQL'
                    UPDATE player_gang_members
                    SET
                        status = 'busy',
                        current_assignment_type = 'dirty_job',
                        current_assignment_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND user_id = ?
                      AND status = 'active'
                SQL
            );

            foreach ($assignments as $assignment) {
                $memberUpdate->execute([
                    $runId,
                    $assignment['gang_member_id'],
                    $freshUser['id'],
                ]);

                if ($memberUpdate->rowCount() !== 1) {
                    throw new RuntimeException('A selected crew member became unavailable.');
                }
            }

            $runUpdate = $pdo->prepare(
                <<<'SQL'
                    UPDATE dirty_job_runs
                    SET
                        status = 'executing',
                        execution_started_at = NOW(),
                        completes_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            );
            $runUpdate->execute([$duration, $runId]);

            AuditService::log(
                (int) $freshUser['id'],
                'dirty_job.execute',
                [
                    'run_id' => $runId,
                    'duration_seconds' => $duration,
                    'crew_count' => count($assignments),
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Dirty Job execution started.',
                'duration_seconds' => $duration,
                'run' => $this->hydrateRun(
                    $this->findRun($runId, (int) $freshUser['id'])
                ),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function submitDecision(
        array $user,
        int $runId,
        string $decisionCode
    ): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRunWithTemplate($runId, (int) $user['id']);

            if ($run['status'] !== 'awaiting_decision') {
                throw new RuntimeException('This Dirty Job is not waiting for a decision.');
            }

            $event = $this->decodeJson($run['event_definition']);
            $decision = $this->findDecision($event, $decisionCode);

            if ($decision === null) {
                throw new RuntimeException('Invalid event decision.');
            }

            $pdo->prepare(
                <<<'SQL'
                    UPDATE dirty_job_runs
                    SET
                        selected_decision_code = ?,
                        status = 'executing',
                        completes_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$decisionCode, $runId]);

            $pdo->commit();

            return [
                'message' => 'Decision saved. The job is ready to resolve.',
                'decision' => $decision,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function resolve(array $user, int $runId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRunWithTemplate($runId, (int) $user['id']);

            if ($run['status'] === 'awaiting_decision') {
                throw new RuntimeException('Choose an event response before resolving the job.');
            }

            if ($run['status'] !== 'executing') {
                throw new RuntimeException('This Dirty Job has already been resolved or is not executing.');
            }

            if ((int) ($run['seconds_remaining'] ?? 0) > 0) {
                throw new RuntimeException('Dirty Job execution is not complete yet.');
            }

            $event = $this->decodeJson($run['event_definition']);

            if ($event !== [] && $run['selected_decision_code'] === null) {
                $pdo->prepare(
                    <<<'SQL'
                        UPDATE dirty_job_runs
                        SET status = 'awaiting_decision', updated_at = NOW()
                        WHERE id = ?
                    SQL
                )->execute([$runId]);

                $pdo->commit();

                return [
                    'message' => 'The operation requires a decision.',
                    'status' => 'awaiting_decision',
                    'event' => $event,
                ];
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $district = $this->loadDistrict((int) $run['territory_id']);
            $assignments = $this->loadAssignments($runId, true);
            $preparations = $this->loadPreparations($runId);
            $equipment = $this->loadRunEquipment($runId);
            $decision = $this->findDecision(
                $event,
                (string) ($run['selected_decision_code'] ?? '')
            ) ?? [];

            $calculation = $this->calculator->calculate(
                $run,
                $freshUser,
                $district,
                $assignments,
                $preparations,
                $equipment,
                $decision
            );

            $result = $this->applyOutcome(
                $freshUser,
                $run,
                $assignments,
                $equipment,
                $calculation
            );

            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function activeRuns(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT run.*
                FROM dirty_job_runs run
                WHERE run.user_id = ?
                  AND run.status IN (
                    'accepted',
                    'preparing',
                    'ready',
                    'executing',
                    'awaiting_decision'
                  )
                ORDER BY run.id DESC
            SQL
        );
        $statement->execute([$user['id']]);
        $runs = $statement->fetchAll();

        foreach ($runs as &$run) {
            $run = $this->hydrateRun($run);
        }

        return $runs;
    }

    public function history(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    run.*,
                    template.title,
                    template.category,
                    territory.name AS territory_name
                FROM dirty_job_runs run
                JOIN dirty_job_opportunities opportunity
                    ON opportunity.id = run.opportunity_id
                JOIN dirty_job_templates template
                    ON template.id = opportunity.template_id
                JOIN territories territory
                    ON territory.id = opportunity.territory_id
                WHERE run.user_id = ?
                  AND run.status IN (
                    'completed',
                    'partially_completed',
                    'failed',
                    'cancelled',
                    'expired'
                  )
                ORDER BY run.resolved_at DESC, run.id DESC
                LIMIT 100
            SQL
        );
        $statement->execute([$user['id']]);
        $history = $statement->fetchAll();

        foreach ($history as &$run) {
            $run['result'] = $this->decodeJson($run['result']);
        }

        return $history;
    }

    private function applyOutcome(
        array $user,
        array $run,
        array $assignments,
        array $equipment,
        array $calculation
    ): array {
        $outcome = $calculation['outcome'];
        $outcomeRewardFactor = match ($outcome) {
            'critical_success' => 1.25,
            'success' => 1.0,
            'partial_success' => 0.45,
            default => 0.0,
        };

        $baseReward = $this->random->integer(
            (int) $run['reward_min'],
            (int) $run['reward_max']
        );
        $reward = (int) round(
            $baseReward
            * (float) $run['reward_multiplier']
            * (float) $calculation['reward_modifier']
            * $outcomeRewardFactor
        );

        $dirtyMoneyPercent = min(100, (int) $run['dirty_money_percent']);
        $dirtyCashReward = (int) floor($reward * ($dirtyMoneyPercent / 100));
        $cashReward = $reward - $dirtyCashReward;
        $reputation = $outcomeRewardFactor > 0
            ? (int) round((int) $run['reputation_gain'] * $outcomeRewardFactor)
            : 0;
        $experience = $outcome === 'critical_failure'
            ? max(1, (int) round((int) $run['experience_gain'] * 0.35))
            : max(1, (int) round((int) $run['experience_gain'] * max(0.5, $outcomeRewardFactor)));

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    cash = cash + ?,
                    dirty_money = dirty_money + ?,
                    heat = heat + ?,
                    reputation = GREATEST(0, reputation + ?),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([
            $cashReward,
            $dirtyCashReward,
            $calculation['heat'],
            $reputation,
            $user['id'],
        ]);

        $this->experience->grantPlayer(
            (int) $user['id'],
            $experience,
            'dirty_job',
            (int) $run['id'],
            'Dirty Job outcome: ' . $outcome
        );

        (new HeatPressureService())->recordCrimeHeat(
            (int) $user['id'],
            'dirty_job',
            (int) $run['id'],
            (int) $calculation['heat'],
            'Dirty Job heat: ' . $run['title'],
            array_map(static fn (array $assignment): int => (int) $assignment['gang_member_id'], $assignments ?? []),
            null,
            (string) $run['category']
        );

        $physicalRewards = $this->grantPhysicalRewards(
            $user,
            $run,
            $outcome
        );
        $crewConsequences = $this->applyCrewConsequences(
            $user,
            $run,
            $assignments,
            $outcome,
            (int) $calculation['injury_modifier'],
            $experience,
            $reward
        );
        $equipmentConsequences = $this->applyEquipmentConsequences(
            $run,
            $equipment,
            $outcome
        );

        $status = match ($outcome) {
            'critical_success', 'success' => 'completed',
            'partial_success' => 'partially_completed',
            default => 'failed',
        };

        $resultText = $this->resultText($run, $outcome);
        $resultPayload = [
            'outcome' => $outcome,
            'result_text' => $resultText,
            'success_chance' => $calculation['success_chance'],
            'roll' => $calculation['roll'],
            'physical_rewards' => $physicalRewards,
            'crew_consequences' => $crewConsequences,
            'equipment_consequences' => $equipmentConsequences,
        ];

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE dirty_job_runs
                SET
                    status = ?,
                    calculated_success_chance = ?,
                    outcome = ?,
                    cash_reward = ?,
                    dirty_cash_reward = ?,
                    heat_gained = ?,
                    experience_gained = ?,
                    reputation_gained = ?,
                    result = ?,
                    resolved_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([
            $status,
            $calculation['success_chance'],
            $outcome,
            $cashReward,
            $dirtyCashReward,
            $calculation['heat'],
            $experience,
            $reputation,
            json_encode($resultPayload),
            $run['id'],
        ]);

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE dirty_job_opportunities
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([
            $status === 'failed' ? 'failed' : 'completed',
            $run['opportunity_id'],
        ]);

        $this->updateContactRelationship($user, $run, $status);

        if ($reward > 0) {
            (new EconomyLedgerService())->record(
                'dirty_job_reward',
                $reward,
                "Dirty Job reward: {$run['title']}",
                [
                    'source_type' => $run['contact_id'] ? 'npc_contact' : 'external_contract',
                    'source_id' => $run['contact_id'],
                    'destination_type' => 'player',
                    'destination_id' => $user['id'],
                    'user_id' => $user['id'],
                    'territory_id' => $run['territory_id'],
                ]
            );
        }

        AuditService::log(
            (int) $user['id'],
            'dirty_job.resolve',
            [
                'run_id' => $run['id'],
                'outcome' => $outcome,
                'cash_reward' => $cashReward,
                'dirty_cash_reward' => $dirtyCashReward,
                'heat' => $calculation['heat'],
            ]
        );

        return [
            'message' => 'Dirty Job resolved.',
            'status' => $status,
            'outcome' => $outcome,
            'result_text' => $resultText,
            'cash_reward' => $cashReward,
            'dirty_cash_reward' => $dirtyCashReward,
            'heat_gained' => $calculation['heat'],
            'experience_gained' => $experience,
            'reputation_gained' => $reputation,
            'physical_rewards' => $physicalRewards,
            'crew_consequences' => $crewConsequences,
            'equipment_consequences' => $equipmentConsequences,
            'estimated_success_chance' => $calculation['success_chance'],
        ];
    }

    private function grantPhysicalRewards(
        array $user,
        array $run,
        string $outcome
    ): array {
        if (!in_array($outcome, ['critical_success', 'success', 'partial_success'], true)) {
            return [];
        }

        $definition = $this->decodeJson($run['reward_definition']);

        if ($definition === []) {
            return [];
        }

        $quantityFactor = $outcome === 'partial_success' ? 0.5 : 1.0;
        $bonus = $outcome === 'critical_success' ? 1 : 0;
        $rewards = [];

        if (isset($definition['item_code'])) {
            $itemStatement = Database::pdo()->prepare(
                'SELECT * FROM item_definitions WHERE code = ? LIMIT 1'
            );
            $itemStatement->execute([$definition['item_code']]);
            $item = $itemStatement->fetch();

            if ($item) {
                $quantity = max(
                    1,
                    (int) floor(
                        $this->random->integer(
                            (int) ($definition['quantity_min'] ?? 1),
                            (int) ($definition['quantity_max'] ?? 1)
                        ) * $quantityFactor
                    ) + $bonus
                );

                $this->addUserItem(
                    (int) $user['id'],
                    (int) $item['id'],
                    $quantity
                );

                $rewards[] = [
                    'type' => 'item',
                    'id' => (int) $item['id'],
                    'name' => $item['name'],
                    'quantity' => $quantity,
                    'storage_location' => 'personal_inventory',
                ];
            }
        }

        if (isset($definition['drug_id'])) {
            $quantity = max(
                1,
                (int) floor(
                    $this->random->integer(
                        (int) ($definition['quantity_min'] ?? 1),
                        (int) ($definition['quantity_max'] ?? 1)
                    ) * $quantityFactor
                ) + $bonus
            );

            if (($definition['warehouse_only'] ?? false) === true) {
                $warehouse = (new WarehouseService())->firstWarehouseForUser(
                    (int) $user['id']
                );

                if ($warehouse === null) {
                    throw new RuntimeException(
                        'The production reward requires an owned warehouse.'
                    );
                }

                $this->depositDrugReward(
                    (int) $warehouse['id'],
                    (int) $definition['drug_id'],
                    $quantity,
                    (int) $user['id']
                );
                $location = 'warehouse';
            } else {
                $this->addUserDrug(
                    (int) $user['id'],
                    (int) $definition['drug_id'],
                    $quantity
                );
                $location = 'personal_inventory';
            }

            $drugStatement = Database::pdo()->prepare(
                'SELECT name FROM drugs WHERE id = ?'
            );
            $drugStatement->execute([$definition['drug_id']]);

            $rewards[] = [
                'type' => 'drug',
                'id' => (int) $definition['drug_id'],
                'name' => $drugStatement->fetchColumn() ?: 'Unknown drug',
                'quantity' => $quantity,
                'storage_location' => $location,
            ];
        }

        if (isset($definition['vehicle_name'])) {
            $condition = $this->random->integer(
                (int) ($definition['condition_min'] ?? 40),
                (int) ($definition['condition_max'] ?? 70)
            );
            $warehouse = (new WarehouseService())->firstWarehouseForUser(
                (int) $user['id']
            );
            $warehouseId = null;
            $status = 'unsecured';

            if ($warehouse !== null) {
                $usedSlots = $this->vehicleSlotsUsed((int) $warehouse['id']);

                if ($usedSlots < (int) $warehouse['vehicle_capacity']) {
                    $warehouseId = (int) $warehouse['id'];
                    $status = 'stored';
                }
            }

            $statement = Database::pdo()->prepare(
                <<<'SQL'
                    INSERT INTO vehicles (
                        user_id,
                        warehouse_id,
                        name,
                        vehicle_type,
                        condition_rating,
                        estimated_value,
                        stolen,
                        evidence_level,
                        status,
                        acquired_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, 20, ?, NOW(), NOW())
                SQL
            );
            $statement->execute([
                $user['id'],
                $warehouseId,
                $definition['vehicle_name'],
                $definition['vehicle_type'] ?? 'car',
                $condition,
                $definition['estimated_value'] ?? 0,
                $status,
            ]);

            $rewards[] = [
                'type' => 'vehicle',
                'id' => (int) Database::pdo()->lastInsertId(),
                'name' => $definition['vehicle_name'],
                'quantity' => 1,
                'storage_location' => $status,
            ];
        }

        return $rewards;
    }

    private function applyCrewConsequences(
        array $user,
        array $run,
        array $assignments,
        string $outcome,
        int $injuryModifier,
        int $experience,
        int $totalReward
    ): array {
        if ($assignments === []) {
            return [];
        }

        $consequences = [];
        $baseInjuryRisk = match ($outcome) {
            'partial_success' => 8,
            'failure' => 24,
            'critical_failure' => 52,
            default => 2,
        };
        $injuryRisk = max(0, min(80, $baseInjuryRisk + $injuryModifier));
        $targetIndex = $this->random->integer(0, count($assignments) - 1);
        $targetMemberId = (int) $assignments[$targetIndex]['gang_member_id'];
        $arrestApplied = false;
        $injuryApplied = false;

        if ($outcome === 'critical_failure' && $this->random->integer(1, 100) <= 30) {
            $releaseAt = (new DateTimeImmutable())
                ->add(new DateInterval('P1D'))
                ->format('Y-m-d H:i:s');

            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE player_gang_members
                    SET
                        status = 'arrested',
                        arrests = arrests + 1,
                        arrested_until = ?,
                        current_assignment_type = NULL,
                        current_assignment_id = NULL,
                        jobs_failed = jobs_failed + 1,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$releaseAt, $targetMemberId]);

            $this->experience->grantCrew(
                (int) $user['id'],
                $targetMemberId,
                $experience,
                'dirty_job',
                (int) $run['id'],
                'Dirty Job outcome while arrested: ' . $run['title']
            );

            (new CrewHistoryService())->record(
                $targetMemberId,
                (int) $user['id'],
                'arrested',
                'Arrested after a Dirty Job',
                "The member was arrested during {$run['title']} and is expected to return after a short sentence.",
                ['release_at' => $releaseAt],
                (int) $run['id']
            );

            $consequences[] = [
                'member_id' => $targetMemberId,
                'type' => 'arrest',
                'release_at' => $releaseAt,
            ];
            $arrestApplied = true;
        } elseif ($this->random->integer(1, 100) <= $injuryRisk) {
            $healthLoss = $this->random->integer(8, $outcome === 'critical_failure' ? 28 : 18);
            $recoveryHours = $this->random->integer(2, $outcome === 'critical_failure' ? 12 : 7);
            $recoverAt = (new DateTimeImmutable())
                ->add(new DateInterval("PT{$recoveryHours}H"))
                ->format('Y-m-d H:i:s');

            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE player_gang_members
                    SET
                        status = 'injured',
                        health = GREATEST(1, health - ?),
                        injuries = injuries + 1,
                        recovering_until = ?,
                        current_assignment_type = NULL,
                        current_assignment_id = NULL,
                        jobs_failed = jobs_failed + 1,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $healthLoss,
                $recoverAt,
                $targetMemberId,
            ]);

            $this->experience->grantCrew(
                (int) $user['id'],
                $targetMemberId,
                $experience,
                'dirty_job',
                (int) $run['id'],
                'Dirty Job outcome while injured: ' . $run['title']
            );

            (new CrewHistoryService())->record(
                $targetMemberId,
                (int) $user['id'],
                'injured',
                'Injured during a Dirty Job',
                "The member was injured during {$run['title']} and needs time to recover.",
                [
                    'health_loss' => $healthLoss,
                    'recover_at' => $recoverAt,
                ],
                (int) $run['id']
            );

            $consequences[] = [
                'member_id' => $targetMemberId,
                'type' => 'injury',
                'health_loss' => $healthLoss,
                'recover_at' => $recoverAt,
            ];
            $injuryApplied = true;
        }

        $completed = in_array($outcome, ['critical_success', 'success'], true);
        $earningsShare = $assignments === []
            ? 0
            : (int) floor($totalReward / max(1, count($assignments)));

        foreach ($assignments as $assignment) {
            $memberId = (int) $assignment['gang_member_id'];

            if (
                ($arrestApplied || $injuryApplied)
                && $memberId === $targetMemberId
            ) {
                continue;
            }

            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE player_gang_members
                    SET
                        status = 'active',
                        current_assignment_type = NULL,
                        current_assignment_id = NULL,
                        jobs_completed = jobs_completed + ?,
                        jobs_failed = jobs_failed + ?,
                        total_earnings = total_earnings + ?,
                        morale = GREATEST(0, LEAST(100, morale + ?)),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $completed ? 1 : 0,
                $completed ? 0 : 1,
                $earningsShare,
                $completed ? 2 : -2,
                $memberId,
            ]);

            $this->experience->grantCrew(
                (int) $user['id'],
                $memberId,
                $experience,
                'dirty_job',
                (int) $run['id'],
                'Participated in Dirty Job: ' . $run['title']
            );

            (new CrewHistoryService())->record(
                $memberId,
                (int) $user['id'],
                $completed ? 'job_completed' : 'job_failed',
                $completed ? 'Dirty Job completed' : 'Dirty Job failed',
                "Participated as {$assignment['role_code']} in {$run['title']}.",
                [
                    'outcome' => $outcome,
                    'role' => $assignment['role_code'],
                ],
                (int) $run['id']
            );
        }

        return $consequences;
    }

    private function applyEquipmentConsequences(
        array $run,
        array $equipment,
        string $outcome
    ): array {
        $consequences = [];
        $damageRange = match ($outcome) {
            'critical_success' => [0, 1],
            'success' => [0, 3],
            'partial_success' => [2, 8],
            'failure' => [5, 12],
            'critical_failure' => [10, 25],
            default => [0, 0],
        };

        foreach ($equipment as $entry) {
            $damage = $this->random->integer($damageRange[0], $damageRange[1]);
            $durabilityAfter = max(0, (int) $entry['durability_before'] - $damage);
            $lost = $durabilityAfter === 0
                || (
                    $outcome === 'critical_failure'
                    && $this->random->integer(1, 100) <= 10
                );

            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE dirty_job_equipment
                    SET
                        durability_after = ?,
                        lost = ?
                    WHERE id = ?
                SQL
            )->execute([
                $lost ? 0 : $durabilityAfter,
                $lost ? 1 : 0,
                $entry['id'],
            ]);

            if ($lost) {
                $this->removeLostAssetFromInventory($entry);

                Database::pdo()->prepare(
                    'DELETE FROM crew_equipment WHERE id = ?'
                )->execute([$entry['crew_equipment_id']]);
            } else {
                Database::pdo()->prepare(
                    <<<'SQL'
                        UPDATE crew_equipment
                        SET durability = ?
                        WHERE id = ?
                    SQL
                )->execute([
                    $durabilityAfter,
                    $entry['crew_equipment_id'],
                ]);
            }

            if ($damage > 0 || $lost) {
                $consequences[] = [
                    'crew_equipment_id' => (int) $entry['crew_equipment_id'],
                    'name' => $entry['name'] ?? 'Equipment',
                    'damage' => $damage,
                    'durability_after' => $lost ? 0 : $durabilityAfter,
                    'lost' => $lost,
                ];
            }
        }

        return $consequences;
    }

    private function removeLostAssetFromInventory(array $entry): void
    {
        if (($entry['asset_type'] ?? null) === 'item') {
            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE user_items inventory
                    JOIN player_gang_members member
                        ON member.user_id = inventory.user_id
                    SET inventory.quantity = GREATEST(0, inventory.quantity - 1)
                    WHERE member.id = ?
                      AND inventory.item_definition_id = ?
                SQL
            )->execute([
                $entry['gang_member_id'],
                $entry['asset_id'],
            ]);

            return;
        }

        if (($entry['asset_type'] ?? null) === 'weapon') {
            Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE user_weapons inventory
                    JOIN player_gang_members member
                        ON member.user_id = inventory.user_id
                    SET inventory.quantity = GREATEST(0, inventory.quantity - 1)
                    WHERE member.id = ?
                      AND inventory.weapon_id = ?
                SQL
            )->execute([
                $entry['gang_member_id'],
                $entry['asset_id'],
            ]);
        }
    }

    private function validateExecutionRequirements(
        array $user,
        array $run,
        array $assignments
    ): void {
        if (count($assignments) < (int) $run['min_crew_size']) {
            throw new RuntimeException('Not enough crew members are assigned.');
        }

        $requiredRoles = $this->decodeJson($run['required_roles']);
        $assignedRoles = array_column($assignments, 'role_code');

        foreach ($requiredRoles as $role) {
            if (!in_array($role, $assignedRoles, true)) {
                throw new RuntimeException("The {$role} role must be assigned.");
            }
        }

        if ((bool) $run['requires_warehouse']) {
            $warehouse = (new WarehouseService())->firstWarehouseForUser(
                (int) $user['id']
            );

            if ($warehouse === null) {
                throw new RuntimeException('This operation requires an owned warehouse.');
            }

            $definition = $this->decodeJson($run['reward_definition']);

            if (($definition['warehouse_only'] ?? false) === true) {
                $maxQuantity = (int) ($definition['quantity_max'] ?? 1);
                $neededUnits = $maxQuantity
                    * (GameConfig::WAREHOUSE_DRUG_UNITS_PER_TEN / 10);
                $availableUnits = (float) $warehouse['storage_capacity']
                    - (new WarehouseService())->usedStorageUnits((int) $warehouse['id']);

                if ($availableUnits < $neededUnits) {
                    throw new RuntimeException(
                        'The warehouse needs more free capacity for the planned output.'
                    );
                }
            }
        }

        $requiredItems = $this->decodeJson($run['required_items']);

        foreach ($requiredItems as $itemCode) {
            if (!$this->userHasItemCode((int) $user['id'], (string) $itemCode)) {
                throw new RuntimeException("Required item is missing: {$itemCode}.");
            }
        }

        foreach ($assignments as $assignment) {
            if ($assignment['status'] !== 'active') {
                throw new RuntimeException('An assigned crew member is unavailable.');
            }

            if ((int) $assignment['health'] < 30) {
                throw new RuntimeException('An assigned crew member is too injured to work.');
            }
        }
    }

    private function validateUnlockRequirements(array $user, array $opportunity): void
    {
        if ((int) $user['level'] < (int) $opportunity['min_level']) {
            throw new RuntimeException('Player level is too low for this Dirty Job.');
        }

        if ((int) $user['reputation'] < (int) $opportunity['min_reputation']) {
            throw new RuntimeException('Player reputation is too low for this Dirty Job.');
        }

        if (
            $this->availableCrewCount((int) $user['id'])
            < (int) $opportunity['min_crew_size']
        ) {
            throw new RuntimeException('This Dirty Job requires a larger available crew.');
        }

        if ((bool) $opportunity['requires_warehouse']) {
            $warehouse = (new WarehouseService())->firstWarehouseForUser(
                (int) $user['id']
            );

            if ($warehouse === null) {
                throw new RuntimeException('This Dirty Job requires an owned warehouse.');
            }
        }
    }

    private function validateCrewMemberForAssignment(
        int $userId,
        int $runId,
        int $memberId
    ): void {
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

        if (!$member) {
            throw new RuntimeException('Selected crew member was not found.');
        }

        if ($member['status'] !== 'active') {
            throw new RuntimeException('Selected crew member is unavailable.');
        }

        if (
            $member['current_assignment_id'] !== null
            && (
                $member['current_assignment_type'] !== 'dirty_job'
                || (int) $member['current_assignment_id'] !== $runId
            )
        ) {
            throw new RuntimeException('Selected crew member is already assigned elsewhere.');
        }
    }

    private function normalizeAssignments(array $assignments): array
    {
        $allowedRoles = array_keys(GameConfig::crewRoleDefinitions());
        $normalized = [];

        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                throw new RuntimeException('Invalid crew assignment payload.');
            }

            $memberId = (int) ($assignment['member_id'] ?? 0);
            $roleCode = trim((string) ($assignment['role_code'] ?? ''));

            if ($memberId <= 0 || !in_array($roleCode, $allowedRoles, true)) {
                throw new RuntimeException('Invalid crew member or role.');
            }

            $normalized[] = [
                'member_id' => $memberId,
                'role_code' => $roleCode,
            ];
        }

        return $normalized;
    }

    private function loadAssignments(int $runId, bool $withTraits = false): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    assignment.id,
                    assignment.dirty_job_run_id,
                    assignment.gang_member_id,
                    assignment.role_code,
                    member.*,
                    npc.first_name,
                    npc.last_name,
                    npc.nickname
                FROM dirty_job_assignments assignment
                JOIN player_gang_members member
                    ON member.id = assignment.gang_member_id
                JOIN npcs npc
                    ON npc.id = member.npc_id
                WHERE assignment.dirty_job_run_id = ?
                ORDER BY assignment.id
            SQL
        );
        $statement->execute([$runId]);
        $assignments = $statement->fetchAll();

        if (!$withTraits) {
            return $assignments;
        }

        foreach ($assignments as &$assignment) {
            $traits = $this->loadTraitEffects((int) $assignment['npc_id']);
            $assignment['trait_effects'] = $traits;
            $assignment['member'] = $assignment;
        }

        return $assignments;
    }

    private function loadTraitEffects(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT trait.effects
                FROM npc_trait_assignments assignment
                JOIN npc_traits trait ON trait.id = assignment.trait_id
                WHERE assignment.npc_id = ?
            SQL
        );
        $statement->execute([$npcId]);
        $effects = [];

        foreach ($statement->fetchAll() as $row) {
            $decoded = $this->decodeJson($row['effects']);

            foreach ($decoded as $key => $value) {
                if (!is_numeric($value)) {
                    continue;
                }

                $effects[$key] = ($effects[$key] ?? 0) + (float) $value;
            }
        }

        return $effects;
    }

    private function loadPreparations(int $runId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM dirty_job_preparations
                WHERE dirty_job_run_id = ?
                ORDER BY id
            SQL
        );
        $statement->execute([$runId]);
        $preparations = $statement->fetchAll();

        foreach ($preparations as &$preparation) {
            $preparation['details'] = $this->decodeJson($preparation['details']);
        }

        return $preparations;
    }

    private function loadRunEquipment(int $runId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    snapshot.*,
                    COALESCE(item.name, weapon.name) AS name,
                    equipment.asset_type,
                    equipment.asset_id
                FROM dirty_job_equipment snapshot
                LEFT JOIN crew_equipment equipment
                    ON equipment.id = snapshot.crew_equipment_id
                LEFT JOIN item_definitions item
                    ON equipment.asset_type = 'item'
                    AND item.id = equipment.asset_id
                LEFT JOIN weapons weapon
                    ON equipment.asset_type = 'weapon'
                    AND weapon.id = equipment.asset_id
                WHERE snapshot.dirty_job_run_id = ?
                ORDER BY snapshot.id
            SQL
        );
        $statement->execute([$runId]);
        $equipment = $statement->fetchAll();

        foreach ($equipment as &$entry) {
            $entry['effects'] = $this->decodeJson($entry['effects_snapshot']);
        }

        return $equipment;
    }

    private function hydrateRun(array $run): array
    {
        $contextStatement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    template.title,
                    template.category,
                    template.short_description,
                    template.event_definition,
                    opportunity.territory_id,
                    territory.name AS territory_name
                FROM dirty_job_opportunities opportunity
                JOIN dirty_job_templates template
                    ON template.id = opportunity.template_id
                JOIN territories territory
                    ON territory.id = opportunity.territory_id
                WHERE opportunity.id = ?
            SQL
        );
        $contextStatement->execute([$run['opportunity_id']]);
        $context = $contextStatement->fetch() ?: [];
        $run = array_merge($run, $context);
        $run['result'] = $this->decodeJson($run['result'] ?? null);
        $run['event'] = $this->decodeJson($run['event_definition'] ?? null);
        $run['seconds_remaining'] = $run['completes_at']
            ? max(0, strtotime($run['completes_at']) - time())
            : null;
        $run['preparations'] = $this->loadPreparations((int) $run['id']);
        $run['assignments'] = $this->loadAssignments((int) $run['id']);
        $run['equipment'] = $this->loadRunEquipment((int) $run['id']);

        return $run;
    }

    private function formatOpportunity(array $opportunity): array
    {
        if (isset($opportunity['opportunity_id'])) {
            $opportunity['id'] = (int) $opportunity['opportunity_id'];
        }

        foreach ([
            'required_roles',
            'required_items',
            'preparation_options',
            'event_definition',
            'reward_definition',
            'narrative_variables',
        ] as $field) {
            $opportunity[$field] = $this->decodeJson($opportunity[$field] ?? null);
        }

        $opportunity['contact_name'] = $this->contactName($opportunity);

        return $opportunity;
    }

    private function loadOpportunity(int $userId, int $opportunityId): ?array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    opportunity.*,
                    template.*,
                    opportunity.id AS opportunity_id,
                    opportunity.status AS opportunity_status,
                    territory.name AS territory_name,
                    territory.police_presence,
                    territory.wealth,
                    territory.crime_rate,
                    contact.id AS contact_id,
                    npc.first_name AS contact_first_name,
                    npc.last_name AS contact_last_name,
                    npc.nickname AS contact_nickname,
                    npc.biography AS contact_biography,
                    contact.contact_type,
                    contact.payment_reliability,
                    contact.criminal_connections
                FROM dirty_job_opportunities opportunity
                JOIN dirty_job_templates template
                    ON template.id = opportunity.template_id
                JOIN territories territory
                    ON territory.id = opportunity.territory_id
                LEFT JOIN npc_contacts contact
                    ON contact.id = opportunity.contact_id
                LEFT JOIN npcs npc
                    ON npc.id = contact.npc_id
                WHERE opportunity.id = ?
                  AND opportunity.user_id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$opportunityId, $userId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $row['status'] = $row['opportunity_status'];

        return $row;
    }

    private function lockOpportunity(int $userId, int $opportunityId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    opportunity.*,
                    template.*,
                    opportunity.id AS opportunity_id,
                    opportunity.status AS opportunity_status
                FROM dirty_job_opportunities opportunity
                JOIN dirty_job_templates template
                    ON template.id = opportunity.template_id
                WHERE opportunity.id = ?
                  AND opportunity.user_id = ?
                FOR UPDATE
            SQL
        );
        $statement->execute([$opportunityId, $userId]);
        $opportunity = $statement->fetch();

        if (!$opportunity) {
            throw new RuntimeException('Dirty Job opportunity not found.');
        }

        $opportunity['status'] = $opportunity['opportunity_status'];

        return $opportunity;
    }

    private function lockRunWithTemplate(int $runId, int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    run.*,
                    TIMESTAMPDIFF(SECOND, NOW(), run.completes_at) AS seconds_remaining,
                    opportunity.template_id,
                    opportunity.contact_id,
                    opportunity.territory_id,
                    opportunity.reward_multiplier,
                    opportunity.risk_modifier,
                    template.code,
                    template.category,
                    template.tier,
                    template.title,
                    template.short_description,
                    template.introduction,
                    template.briefing,
                    template.preparation_text,
                    template.execution_text,
                    template.success_text,
                    template.partial_success_text,
                    template.failure_text,
                    template.critical_failure_text,
                    template.duration_seconds,
                    template.energy_cost,
                    template.reward_min,
                    template.reward_max,
                    template.dirty_money_percent,
                    template.experience_gain,
                    template.reputation_gain,
                    template.base_success_rate,
                    template.difficulty,
                    template.heat_min,
                    template.heat_max,
                    template.min_level,
                    template.min_reputation,
                    template.min_crew_size,
                    template.required_roles,
                    template.required_items,
                    template.preparation_options,
                    template.event_definition,
                    template.reward_definition,
                    template.requires_warehouse
                FROM dirty_job_runs run
                JOIN dirty_job_opportunities opportunity
                    ON opportunity.id = run.opportunity_id
                JOIN dirty_job_templates template
                    ON template.id = opportunity.template_id
                WHERE run.id = ?
                  AND run.user_id = ?
                FOR UPDATE
            SQL
        );
        $statement->execute([$runId, $userId]);
        $run = $statement->fetch();

        if (!$run) {
            throw new RuntimeException('Dirty Job run not found.');
        }

        return $run;
    }

    private function findRun(int $runId, int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM dirty_job_runs
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$runId, $userId]);
        $run = $statement->fetch();

        if (!$run) {
            throw new RuntimeException('Dirty Job run not found.');
        }

        return $run;
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

    private function loadDistrict(int $territoryId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM territories WHERE id = ? LIMIT 1'
        );
        $statement->execute([$territoryId]);
        $district = $statement->fetch();

        if (!$district) {
            throw new RuntimeException('Dirty Job district not found.');
        }

        return $district;
    }

    private function availableCrewCount(int $userId): int
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM player_gang_members
                WHERE user_id = ?
                  AND status = 'active'
                  AND current_assignment_id IS NULL
                  AND health >= 30
            SQL
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    private function userHasItemCode(int $userId, string $itemCode): bool
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COALESCE(inventory.quantity, 0)
                FROM item_definitions item
                LEFT JOIN user_items inventory
                    ON inventory.item_definition_id = item.id
                    AND inventory.user_id = ?
                WHERE item.code = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$userId, $itemCode]);

        return (int) ($statement->fetchColumn() ?: 0) > 0;
    }

    private function addUserItem(int $userId, int $itemId, int $quantity): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO user_items (
                    user_id,
                    item_definition_id,
                    quantity,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    updated_at = NOW()
            SQL
        )->execute([$userId, $itemId, $quantity]);
    }

    private function addUserDrug(int $userId, int $drugId, int $quantity): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO user_drugs (user_id, drug_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity)
            SQL
        )->execute([$userId, $drugId, $quantity]);
    }

    private function depositDrugReward(
        int $warehouseId,
        int $drugId,
        int $quantity,
        int $userId
    ): void {
        $warehouse = (new WarehouseService())->firstWarehouseForUser($userId);

        if ($warehouse === null || (int) $warehouse['id'] !== $warehouseId) {
            throw new RuntimeException('Reward warehouse was not found.');
        }

        $unitsEach = GameConfig::WAREHOUSE_DRUG_UNITS_PER_TEN / 10;
        $used = (new WarehouseService())->usedStorageUnits($warehouseId);
        $needed = $quantity * $unitsEach;

        if ($used + $needed > (float) $warehouse['storage_capacity']) {
            throw new RuntimeException('Warehouse capacity is insufficient for the reward.');
        }

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO warehouse_storage (
                    warehouse_id,
                    asset_type,
                    asset_id,
                    quantity,
                    reserved_quantity,
                    storage_units_each,
                    created_at,
                    updated_at
                ) VALUES (?, 'drug', ?, ?, 0, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    updated_at = NOW()
            SQL
        )->execute([$warehouseId, $drugId, $quantity, $unitsEach]);

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO storage_logs (
                    warehouse_id,
                    user_id,
                    action,
                    asset_type,
                    asset_id,
                    quantity,
                    description,
                    created_at
                ) VALUES (?, ?, 'reward_deposit', 'drug', ?, ?, ?, NOW())
            SQL
        )->execute([
            $warehouseId,
            $userId,
            $drugId,
            $quantity,
            'Dirty Job production reward deposited into warehouse storage.',
        ]);
    }

    private function vehicleSlotsUsed(int $warehouseId): int
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM vehicles
                WHERE warehouse_id = ?
                  AND status = 'stored'
            SQL
        );
        $statement->execute([$warehouseId]);

        return (int) $statement->fetchColumn();
    }

    private function updateContactRelationship(
        array $user,
        array $run,
        string $status
    ): void {
        if ($run['contact_id'] === null) {
            return;
        }

        $completed = $status !== 'failed';
        $trustChange = $completed ? 4 : -3;

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO contact_relationships (
                    user_id,
                    contact_id,
                    trust,
                    jobs_completed,
                    jobs_failed,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    trust = GREATEST(-100, LEAST(100, trust + VALUES(trust))),
                    jobs_completed = jobs_completed + VALUES(jobs_completed),
                    jobs_failed = jobs_failed + VALUES(jobs_failed),
                    updated_at = NOW()
            SQL
        )->execute([
            $user['id'],
            $run['contact_id'],
            $trustChange,
            $completed ? 1 : 0,
            $completed ? 0 : 1,
        ]);
    }

    private function requirementMessages(
        array $user,
        array $opportunity,
        int $crewCount,
        bool $hasWarehouse
    ): array {
        $messages = [];

        if ((int) $user['level'] < (int) $opportunity['min_level']) {
            $messages[] = "Requires level {$opportunity['min_level']}";
        }

        if ((int) $user['reputation'] < (int) $opportunity['min_reputation']) {
            $messages[] = "Requires reputation {$opportunity['min_reputation']}";
        }

        if ($crewCount < (int) $opportunity['min_crew_size']) {
            $messages[] = "Requires {$opportunity['min_crew_size']} available crew members";
        }

        if ((bool) $opportunity['requires_warehouse'] && !$hasWarehouse) {
            $messages[] = 'Requires an owned warehouse';
        }

        return $messages;
    }

    private function findDecision(array $event, string $decisionCode): ?array
    {
        if ($decisionCode === '') {
            return null;
        }

        foreach (($event['options'] ?? []) as $option) {
            if (($option['code'] ?? null) === $decisionCode) {
                return $option;
            }
        }

        return null;
    }

    private function resultText(array $run, string $outcome): string
    {
        return match ($outcome) {
            'critical_success', 'success' => $run['success_text'],
            'partial_success' => $run['partial_success_text'],
            'critical_failure' => $run['critical_failure_text'],
            default => $run['failure_text'],
        };
    }

    private function contactName(array $row): string
    {
        if (empty($row['contact_first_name'])) {
            return 'World opportunity';
        }

        return trim(
            $row['contact_first_name']
            . ' '
            . (!empty($row['contact_nickname'])
                ? "“{$row['contact_nickname']}” "
                : '')
            . $row['contact_last_name']
        );
    }

    private function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            return sprintf(
                '%s-%s-%s-%s-%s',
                bin2hex(random_bytes(4)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(6))
            );
        }

        if (strlen($key) > 36) {
            throw new RuntimeException('Idempotency key is too long.');
        }

        return $key;
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
