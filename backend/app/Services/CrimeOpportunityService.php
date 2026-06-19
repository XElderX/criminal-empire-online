<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class CrimeOpportunityService
{
    private ExperienceService $experience;

    public function __construct(
        private readonly RandomSource $random = new SecureRandomSource(),
        private readonly ?CrimeRiskCalculator $riskCalculator = null,
        private readonly ?CrimeNarrativeService $narrative = null
    ) {
        $this->experience = new ExperienceService();
    }

    /** @return array<string, mixed> */
    public function overview(array $user): array
    {
        return [
            'legacy_crimes' => $this->legacyCrimes((int) $user['id']),
            'locations' => $this->locations($user),
            'opportunities' => $this->opportunities((int) $user['id']),
            'active_runs' => $this->activeRuns((int) $user['id']),
            'history' => $this->history($user, 8),
            'contacts' => $this->contacts((int) $user['id']),
            'crew' => $this->availableCrew((int) $user['id']),
            'equipment' => $this->availableEquipment((int) $user['id']),
            'preparation_options' => $this->narrative()->preparationOptions(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function legacyCrimes(int $userId): array
    {
        $crimes = Database::pdo()
            ->query('SELECT * FROM crimes ORDER BY energy_cost ASC')
            ->fetchAll();

        foreach ($crimes as &$crime) {
            $crime['cooldown_seconds'] = 600;
            $crime['cooldown'] = $this->legacyCrimeCooldownState($userId, (int) $crime['id']);
        }

        return $crimes;
    }

    private function legacyCrimeCooldownState(int $userId, int $crimeId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    MAX(available_at) AS available_at,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), MAX(available_at))) AS remaining_seconds
                FROM player_action_cooldowns
                WHERE user_id = ?
                  AND action_type = 'legacy_crime'
                  AND action_code = ?
                  AND available_at > NOW()
            SQL
        );
        $statement->execute([$userId, 'crime_' . $crimeId]);
        $row = $statement->fetch();

        $remaining = (int) ($row['remaining_seconds'] ?? 0);

        return [
            'active' => $remaining > 0,
            'remaining_seconds' => $remaining,
            'available_at' => $row['available_at'] ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM crime_discovery_locations
                WHERE active = 1
                ORDER BY min_level, id
            SQL
        );
        $statement->execute();
        $locations = $statement->fetchAll();

        foreach ($locations as &$location) {
            $location['can_explore'] = (int) $user['level'] >= (int) $location['min_level']
                && (int) $user['energy'] >= (int) $location['energy_cost']
                && (int) $user['cash'] >= (int) $location['cash_cost'];
            $location['blocked_reason'] = $this->locationBlockedReason($user, $location);
        }

        return $locations;
    }

    /** @return array<string, mixed> */
    public function explore(array $user, string $locationCode): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $freshUser = $this->lockUser((int) $user['id']);
            $location = $this->locationByCode($locationCode);

            if (!$location) {
                throw new RuntimeException('Discovery location not found.');
            }

            if ((int) $freshUser['level'] < (int) $location['min_level']) {
                throw new RuntimeException('Your level is too low for this location.');
            }

            if ((int) $freshUser['energy'] < (int) $location['energy_cost']) {
                throw new RuntimeException('Not enough energy to explore this location.');
            }

            if ((int) $freshUser['cash'] < (int) $location['cash_cost']) {
                throw new RuntimeException('Not enough cash for this information source.');
            }

            $template = $this->chooseTemplateForLocation($locationCode);
            $territory = $this->chooseTerritory();
            $sourceNpc = $this->chooseSourceNpc($locationCode, (int) $freshUser['id']);
            $relationship = $sourceNpc
                ? $this->ensureRelationship((int) $freshUser['id'], (int) $sourceNpc['id'], $this->sourceRelationshipType($sourceNpc))
                : null;

            $qualityRoll = $this->random->integer(1, 100);
            $trust = (int) ($relationship['trust'] ?? $sourceNpc['reliability'] ?? 50);
            $quality = $this->qualityFromRoll($qualityRoll, $trust, (int) $freshUser['heat']);
            $informationLevel = $this->informationLevelFromQuality($quality, $qualityRoll);
            $rewardNoise = $this->random->integer(85, 120) / 100;
            $heatNoise = $this->random->integer(85, 125) / 100;

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET cash = cash - ?, energy = energy - ?, updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                (int) $location['cash_cost'],
                (int) $location['energy_cost'],
                $freshUser['id'],
            ]);

            $title = $this->opportunityTitle($template, $informationLevel);
            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO crime_opportunities (
                        user_id, template_id, location_id, territory_id, source_npc_id,
                        title, target_name, information_level, status, source_type,
                        source_description, quality, reliability, estimated_reward_min,
                        estimated_reward_max, estimated_heat_min, estimated_heat_max,
                        expires_at, discovered_at, metadata, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, 'known', ?, ?, ?, ?, ?, ?, ?, ?,
                        DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), ?, NOW(), NOW()
                    )
                SQL
            );
            $insert->execute([
                $freshUser['id'],
                $template['id'],
                $location['id'],
                $territory['id'] ?? null,
                $sourceNpc['id'] ?? null,
                $title,
                $this->targetName($template, $territory),
                $informationLevel,
                $this->sourceType($locationCode),
                $this->sourceDescription($location, $sourceNpc, $quality),
                $quality,
                $trust,
                (int) round((int) $template['base_reward_min'] * $rewardNoise),
                (int) round((int) $template['base_reward_max'] * $rewardNoise),
                (int) round((int) $template['base_heat_min'] * $heatNoise),
                (int) round((int) $template['base_heat_max'] * $heatNoise),
                json_encode([
                    'false_lead_possible' => in_array($quality, ['suspicious', 'trap'], true),
                    'discovery_roll' => $qualityRoll,
                    'location_code' => $locationCode,
                ], JSON_THROW_ON_ERROR),
            ]);

            $opportunityId = (int) $pdo->lastInsertId();

            if ($sourceNpc) {
                $this->touchNpc((int) $sourceNpc['id'], 'Shared a crime lead.');
                $this->timeline(
                    (int) $sourceNpc['id'],
                    (int) $freshUser['id'],
                    null,
                    'meeting',
                    'Met during crime discovery',
                    'The player crossed paths with this NPC while looking for opportunities.',
                    ['location' => $locationCode, 'quality' => $quality]
                );
            }

            AuditService::log((int) $freshUser['id'], 'crime.explore', [
                'location' => $locationCode,
                'opportunity_id' => $opportunityId,
                'quality' => $quality,
                'information_level' => $informationLevel,
            ]);

            $pdo->commit();

            return [
                'message' => 'You found a new lead.',
                'opportunity' => $this->opportunity((int) $freshUser['id'], $opportunityId),
                'user_costs' => [
                    'energy' => (int) $location['energy_cost'],
                    'cash' => (int) $location['cash_cost'],
                ],
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function opportunity(int $userId, int $opportunityId): array
    {
        $opportunity = $this->loadOpportunity($userId, $opportunityId);

        if (!$opportunity) {
            throw new RuntimeException('Crime opportunity not found.');
        }

        return $this->formatOpportunity($opportunity);
    }

    /** @return array<string, mixed> */
    public function investigate(array $user, int $opportunityId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $freshUser = $this->lockUser((int) $user['id']);
            $opportunity = $this->lockOpportunity((int) $freshUser['id'], $opportunityId);

            if (!$opportunity) {
                throw new RuntimeException('Crime opportunity not found.');
            }

            if (!in_array($opportunity['status'], ['known', 'investigating'], true)) {
                throw new RuntimeException('This opportunity cannot be investigated now.');
            }

            if ($this->isExpired($opportunity)) {
                $this->expireOpportunity($opportunityId);
                throw new RuntimeException('This opportunity has expired.');
            }

            $cashCost = 45;
            $energyCost = 3;

            if ((int) $freshUser['cash'] < $cashCost) {
                throw new RuntimeException('Not enough cash to investigate.');
            }

            if ((int) $freshUser['energy'] < $energyCost) {
                throw new RuntimeException('Not enough energy to investigate.');
            }

            $pdo->prepare(
                'UPDATE users SET cash = cash - ?, energy = energy - ?, updated_at = NOW() WHERE id = ?'
            )->execute([$cashCost, $energyCost, $freshUser['id']]);

            $nextLevel = $opportunity['quality'] === 'trap'
                ? 'trap'
                : 'confirmed';
            $status = $nextLevel === 'confirmed' ? 'investigating' : 'known';

            $pdo->prepare(
                <<<'SQL'
                    UPDATE crime_opportunities
                    SET information_level = ?, status = ?, investigated_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$nextLevel, $status, $opportunityId]);

            AuditService::log((int) $freshUser['id'], 'crime.investigate', [
                'opportunity_id' => $opportunityId,
                'information_level' => $nextLevel,
            ]);

            $pdo->commit();

            return [
                'message' => $nextLevel === 'trap'
                    ? 'The lead feels suspicious. You can still act, but the risk estimate is worse.'
                    : 'The lead is now confirmed enough to prepare or execute.',
                'opportunity' => $this->opportunity((int) $freshUser['id'], $opportunityId),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function prepare(array $user, int $opportunityId, string $code): array
    {
        $option = $this->preparationOption($code);
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $freshUser = $this->lockUser((int) $user['id']);
            $opportunity = $this->lockOpportunity((int) $freshUser['id'], $opportunityId);

            if (!$opportunity) {
                throw new RuntimeException('Crime opportunity not found.');
            }

            if (!in_array($opportunity['information_level'], ['confirmed', 'trap'], true)) {
                throw new RuntimeException('Investigate this lead before preparing it.');
            }

            if (in_array($opportunity['status'], ['completed', 'abandoned', 'expired', 'active'], true)) {
                throw new RuntimeException('This opportunity cannot be prepared now.');
            }

            if ((int) $freshUser['cash'] < (int) $option['cash_cost']) {
                throw new RuntimeException('Not enough cash for this preparation.');
            }

            if ((int) $freshUser['energy'] < (int) $option['energy_cost']) {
                throw new RuntimeException('Not enough energy for this preparation.');
            }

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET cash = cash - ?, energy = energy - ?, updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                (int) $option['cash_cost'],
                (int) $option['energy_cost'],
                $freshUser['id'],
            ]);

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO crime_preparations (
                        opportunity_id, user_id, code, name, description,
                        cash_cost, energy_cost, effects, applied_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                SQL
            );
            $insert->execute([
                $opportunityId,
                $freshUser['id'],
                $option['code'],
                $option['name'],
                $option['description'],
                (int) $option['cash_cost'],
                (int) $option['energy_cost'],
                json_encode($option['effects'], JSON_THROW_ON_ERROR),
            ]);

            $pdo->prepare(
                "UPDATE crime_opportunities SET status = 'prepared', prepared_at = NOW(), updated_at = NOW() WHERE id = ?"
            )->execute([$opportunityId]);

            AuditService::log((int) $freshUser['id'], 'crime.prepare', [
                'opportunity_id' => $opportunityId,
                'preparation' => $code,
            ]);

            $pdo->commit();

            return [
                'message' => 'Preparation applied.',
                'opportunity' => $this->opportunity((int) $freshUser['id'], $opportunityId),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @param array<int, array<string, mixed>> $assignments */
    public function assignCrew(array $user, int $opportunityId, array $assignments): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $opportunity = $this->lockOpportunity((int) $user['id'], $opportunityId);

            if (!$opportunity) {
                throw new RuntimeException('Crime opportunity not found.');
            }

            if (in_array($opportunity['status'], ['completed', 'abandoned', 'expired', 'active'], true)) {
                throw new RuntimeException('Crew cannot be changed for this opportunity now.');
            }

            $pdo->prepare('DELETE FROM crime_opportunity_assignments WHERE opportunity_id = ? AND user_id = ?')
                ->execute([$opportunityId, $user['id']]);

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO crime_opportunity_assignments (
                        opportunity_id, user_id, gang_member_id, role_code, assigned_at
                    ) VALUES (?, ?, ?, ?, NOW())
                SQL
            );

            foreach ($assignments as $assignment) {
                $memberId = (int) ($assignment['gang_member_id'] ?? 0);
                $roleCode = trim((string) ($assignment['role_code'] ?? 'helper'));

                if ($memberId <= 0) {
                    continue;
                }

                $member = $this->crewMember((int) $user['id'], $memberId);

                if (!$member) {
                    throw new RuntimeException('Crew member not found.');
                }

                if ($member['status'] !== 'active') {
                    throw new RuntimeException('Only active crew members can be assigned.');
                }

                $insert->execute([$opportunityId, $user['id'], $memberId, $roleCode ?: 'helper']);
            }

            AuditService::log((int) $user['id'], 'crime.assign_crew', [
                'opportunity_id' => $opportunityId,
                'count' => count($assignments),
            ]);

            $pdo->commit();

            return [
                'message' => 'Crew assignment saved.',
                'opportunity' => $this->opportunity((int) $user['id'], $opportunityId),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @param array<int, array<string, mixed>> $equipment */
    public function assignEquipment(array $user, int $opportunityId, array $equipment): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $opportunity = $this->lockOpportunity((int) $user['id'], $opportunityId);

            if (!$opportunity) {
                throw new RuntimeException('Crime opportunity not found.');
            }

            if (in_array($opportunity['status'], ['completed', 'abandoned', 'expired', 'active'], true)) {
                throw new RuntimeException('Equipment cannot be changed for this opportunity now.');
            }

            $pdo->prepare('DELETE FROM crime_opportunity_equipment WHERE opportunity_id = ? AND user_id = ?')
                ->execute([$opportunityId, $user['id']]);

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO crime_opportunity_equipment (
                        opportunity_id, user_id, asset_type, asset_id, quantity, selected_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                SQL
            );

            foreach ($equipment as $entry) {
                $assetType = (string) ($entry['asset_type'] ?? 'item');
                $assetId = (int) ($entry['asset_id'] ?? 0);
                $quantity = max(1, (int) ($entry['quantity'] ?? 1));

                if (!in_array($assetType, ['item', 'weapon'], true) || $assetId <= 0) {
                    continue;
                }

                $this->validateOwnedAsset((int) $user['id'], $assetType, $assetId, $quantity);
                $insert->execute([$opportunityId, $user['id'], $assetType, $assetId, $quantity]);
            }

            AuditService::log((int) $user['id'], 'crime.assign_equipment', [
                'opportunity_id' => $opportunityId,
                'count' => count($equipment),
            ]);

            $pdo->commit();

            return [
                'message' => 'Equipment selection saved.',
                'opportunity' => $this->opportunity((int) $user['id'], $opportunityId),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function start(array $user, int $opportunityId, ?string $idempotencyKey = null): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $freshUser = $this->lockUser((int) $user['id']);
            $opportunity = $this->lockOpportunity((int) $freshUser['id'], $opportunityId);

            if (!$opportunity) {
                throw new RuntimeException('Crime opportunity not found.');
            }

            if (!in_array($opportunity['information_level'], ['confirmed', 'trap'], true)) {
                throw new RuntimeException('Investigate this lead before execution.');
            }

            if ($this->isExpired($opportunity)) {
                $this->expireOpportunity($opportunityId);
                throw new RuntimeException('This opportunity has expired.');
            }

            if (in_array($opportunity['status'], ['completed', 'abandoned', 'expired', 'active'], true)) {
                throw new RuntimeException('This opportunity cannot be started.');
            }

            $template = $this->template((int) $opportunity['template_id']);

            if ((int) $freshUser['energy'] < (int) $template['energy_cost']) {
                throw new RuntimeException('Not enough energy to execute this crime.');
            }

            $assignments = $this->opportunityAssignments($opportunityId);
            $equipment = $this->opportunityEquipment($opportunityId);
            $preparations = $this->preparations($opportunityId);

            if (count($assignments) < (int) $template['min_crew']) {
                throw new RuntimeException('This opportunity needs more assigned crew.');
            }

            $risk = $this->riskCalculator()->calculate($template, $assignments, $equipment, $preparations, [
                'heat' => (int) $freshUser['heat'],
                'district_police_presence' => (int) ($opportunity['police_presence'] ?? 40),
                'contact_reliability' => (int) $opportunity['reliability'],
                'quality' => (string) $opportunity['quality'],
            ]);

            $pdo->prepare('UPDATE users SET energy = energy - ?, updated_at = NOW() WHERE id = ?')
                ->execute([(int) $template['energy_cost'], $freshUser['id']]);

            $pdo->prepare("UPDATE crime_opportunities SET status = 'active', updated_at = NOW() WHERE id = ?")
                ->execute([$opportunityId]);

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO crime_runs (
                        user_id, opportunity_id, status, idempotency_key, started_at,
                        success_chance, disaster_chance, police_chance,
                        result, created_at, updated_at
                    ) VALUES (?, ?, 'active', ?, NOW(), ?, ?, ?, ?, NOW(), NOW())
                SQL
            );
            $insert->execute([
                $freshUser['id'],
                $opportunityId,
                $idempotencyKey,
                $risk['success_chance'],
                $risk['disaster_chance'],
                $risk['police_chance'],
                json_encode(['started' => true, 'risk' => $risk], JSON_THROW_ON_ERROR),
            ]);
            $runId = (int) $pdo->lastInsertId();

            $this->copyAssignmentsToRun($runId, $assignments);
            $this->copyEquipmentToRun($runId, $equipment);

            $eventRoll = $this->random->integer(1, 100);
            $eventThreshold = min(75, max(5, $risk['police_chance'] + $risk['witness_risk'] / 2));

            if ($eventRoll <= $eventThreshold) {
                $eventCode = $this->chooseEventCode($template, $risk, $eventRoll);
                $event = $this->narrative()->event($eventCode);
                $this->createRunEvent($runId, $event);

                $pdo->prepare(
                    <<<'SQL'
                        UPDATE crime_runs
                        SET status = 'event_pending', event_code = ?, updated_at = NOW()
                        WHERE id = ?
                    SQL
                )->execute([$eventCode, $runId]);

                $pdo->commit();

                return [
                    'message' => 'A crime event needs a decision.',
                    'run' => $this->run((int) $freshUser['id'], $runId),
                ];
            }

            $result = $this->resolveRunInTransaction($runId, null);
            $pdo->commit();

            return [
                'message' => 'Crime resolved.',
                'run' => $result,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function decide(array $user, int $runId, string $decisionCode): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRun((int) $user['id'], $runId);

            if (!$run) {
                throw new RuntimeException('Crime run not found.');
            }

            if ($run['status'] !== 'event_pending') {
                throw new RuntimeException('This run is not waiting for a decision.');
            }

            $event = $this->pendingEvent($runId);

            if (!$event) {
                throw new RuntimeException('No pending crime event found.');
            }

            $choices = $this->decodeJson($event['choices']);
            $validChoices = array_map(static fn (array $choice): string => (string) $choice['code'], $choices);

            if (!in_array($decisionCode, $validChoices, true)) {
                throw new RuntimeException('Invalid crime decision.');
            }

            $pdo->prepare(
                "UPDATE crime_events SET status = 'resolved', resolved_at = NOW() WHERE id = ?"
            )->execute([$event['id']]);

            $result = $this->resolveRunInTransaction($runId, $decisionCode);
            $pdo->commit();

            return [
                'message' => 'Crime decision resolved.',
                'run' => $result,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function abandon(array $user, int $opportunityId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $opportunity = $this->lockOpportunity((int) $user['id'], $opportunityId);

            if (!$opportunity) {
                throw new RuntimeException('Crime opportunity not found.');
            }

            if (in_array($opportunity['status'], ['completed', 'abandoned', 'expired'], true)) {
                throw new RuntimeException('This opportunity is already closed.');
            }

            $pdo->prepare("UPDATE crime_opportunities SET status = 'abandoned', updated_at = NOW() WHERE id = ?")
                ->execute([$opportunityId]);

            AuditService::log((int) $user['id'], 'crime.abandon', ['opportunity_id' => $opportunityId]);
            $pdo->commit();

            return ['message' => 'Opportunity abandoned.'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function history(array $user, int $limit = 25): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    run.*,
                    opportunity.title,
                    opportunity.target_name,
                    opportunity.quality,
                    template.category,
                    template.tier,
                    territory.name AS territory_name
                FROM crime_runs run
                JOIN crime_opportunities opportunity ON opportunity.id = run.opportunity_id
                JOIN crime_v04_templates template ON template.id = opportunity.template_id
                LEFT JOIN territories territory ON territory.id = opportunity.territory_id
                WHERE run.user_id = ?
                ORDER BY run.id DESC
            SQL
        );
        $statement = Database::pdo()->prepare($statement->queryString . ' LIMIT ' . max(1, (int) $limit));
        $statement->execute([$user['id']]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['result'] = $this->decodeJson($row['result']);
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function contacts(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    relationship.*,
                    npc.first_name,
                    npc.last_name,
                    npc.nickname,
                    npc.age,
                    npc.gender,
                    npc.role,
                    npc.occupation,
                    npc.status,
                    npc.alive,
                    npc.portrait_set_key,
                    npc.portrait_focal_x,
                    npc.portrait_focal_y,
                    territory.name AS territory_name
                FROM npc_relationships relationship
                JOIN npcs npc ON npc.id = relationship.npc_id
                LEFT JOIN territories territory ON territory.id = npc.home_territory_id
                WHERE relationship.user_id = ?
                ORDER BY relationship.trust DESC, relationship.updated_at DESC
                LIMIT 20
            SQL
        );
        $statement->execute([$userId]);
        $rows = $statement->fetchAll();
        $portraitResolver = new CrewPortraitResolver();

        foreach ($rows as &$row) {
            $row['full_name'] = $this->npcName($row);
            $row['portrait'] = $portraitResolver->resolve($row);
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function opportunities(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    opportunity.*,
                    template.code,
                    template.category,
                    template.tier,
                    template.briefing,
                    template.energy_cost,
                    template.base_success_rate,
                    template.base_disaster_chance,
                    template.min_crew,
                    template.max_crew,
                    template.recommended_roles,
                    template.required_items,
                    template.relevant_stats,
                    location.name AS location_name,
                    territory.name AS territory_name,
                    territory.police_presence,
                    npc.first_name AS source_first_name,
                    npc.last_name AS source_last_name,
                    npc.nickname AS source_nickname,
                    npc.role AS source_role,
                    npc.alive AS source_alive
                FROM crime_opportunities opportunity
                JOIN crime_v04_templates template ON template.id = opportunity.template_id
                JOIN crime_discovery_locations location ON location.id = opportunity.location_id
                LEFT JOIN territories territory ON territory.id = opportunity.territory_id
                LEFT JOIN npcs npc ON npc.id = opportunity.source_npc_id
                WHERE opportunity.user_id = ?
                  AND opportunity.status NOT IN ('completed', 'abandoned')
                ORDER BY opportunity.created_at DESC, opportunity.id DESC
            SQL
        );
        $statement->execute([$userId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row = $this->formatOpportunity($row);
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeRuns(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT run.*
                FROM crime_runs run
                WHERE run.user_id = ?
                  AND run.status IN ('active', 'event_pending')
                ORDER BY run.id DESC
            SQL
        );
        $statement->execute([$userId]);
        $runs = $statement->fetchAll();

        foreach ($runs as &$run) {
            $run = $this->hydrateRun($run);
        }

        return $runs;
    }

    /** @return array<string, mixed> */
    public function run(int $userId, int $runId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM crime_runs WHERE id = ? AND user_id = ? LIMIT 1');
        $statement->execute([$runId, $userId]);
        $run = $statement->fetch();

        if (!$run) {
            throw new RuntimeException('Crime run not found.');
        }

        return $this->hydrateRun($run);
    }

    /** @return array<int, array<string, mixed>> */
    public function availableCrew(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT member.id, member.npc_id, npc.role AS role_code, member.status,
                       member.strength, member.shooting, member.driving,
                       member.intelligence, member.stealth, member.intimidation,
                       member.discipline, member.street_knowledge, member.endurance,
                       member.loyalty, member.morale, npc.first_name, npc.last_name, npc.nickname
                FROM player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                WHERE member.user_id = ?
                  AND member.status = 'active'
                ORDER BY member.level DESC, npc.first_name
            SQL
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function availableEquipment(int $userId): array
    {
        $items = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    'item' AS asset_type,
                    item.id AS asset_id,
                    item.name,
                    item.category,
                    item.effects,
                    inventory.quantity,
                    inventory.quantity - COALESCE(equipped.count_equipped, 0) AS available_quantity
                FROM user_items inventory
                JOIN item_definitions item ON item.id = inventory.item_definition_id
                LEFT JOIN (
                    SELECT asset_id, COUNT(*) AS count_equipped
                    FROM crew_equipment
                    WHERE user_id = ? AND asset_type = 'item'
                    GROUP BY asset_id
                ) equipped ON equipped.asset_id = item.id
                WHERE inventory.user_id = ?
                  AND inventory.quantity > 0
            SQL
        );
        $items->execute([$userId, $userId]);
        $itemRows = $items->fetchAll();

        $weapons = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    'weapon' AS asset_type,
                    weapon.id AS asset_id,
                    weapon.name,
                    weapon.class AS category,
                    weapon.effects,
                    inventory.quantity,
                    inventory.quantity - COALESCE(equipped.count_equipped, 0) AS available_quantity
                FROM user_weapons inventory
                JOIN weapons weapon ON weapon.id = inventory.weapon_id
                LEFT JOIN (
                    SELECT asset_id, COUNT(*) AS count_equipped
                    FROM crew_equipment
                    WHERE user_id = ? AND asset_type = 'weapon'
                    GROUP BY asset_id
                ) equipped ON equipped.asset_id = weapon.id
                WHERE inventory.user_id = ?
                  AND inventory.quantity > 0
            SQL
        );
        $weapons->execute([$userId, $userId]);

        return array_values(array_filter(
            array_merge($itemRows, $weapons->fetchAll()),
            static fn (array $row): bool => (int) $row['available_quantity'] > 0
        ));
    }

    /** @return array<string, mixed> */
    private function resolveRunInTransaction(int $runId, ?string $decisionCode): array
    {
        $run = $this->lockRunById($runId);

        if (!$run) {
            throw new RuntimeException('Crime run not found.');
        }

        if ((int) $run['resolved'] === 1) {
            return $this->hydrateRun($run);
        }

        $opportunity = $this->lockOpportunity((int) $run['user_id'], (int) $run['opportunity_id']);
        $template = $this->template((int) $opportunity['template_id']);
        $decisionAdjust = $this->decisionAdjustments($decisionCode);
        $successChance = max(3, min(95, (int) $run['success_chance'] + $decisionAdjust['success']));
        $disasterChance = max(1, min(80, (int) $run['disaster_chance'] + $decisionAdjust['disaster']));
        $policeChance = max(1, min(90, (int) $run['police_chance'] + $decisionAdjust['police']));

        $roll = $this->random->integer(1, 100);
        $outcome = $this->outcomeFromRoll($roll, $successChance, $disasterChance, $opportunity['quality']);
        $baseReward = $this->rewardForOutcome($template, $outcome, $decisionAdjust['loot']);
        $heat = $this->heatForOutcome($template, $outcome, $policeChance, $decisionAdjust['heat']);
        $experience = match ($outcome) {
            'critical_success' => 24,
            'success' => 18,
            'partial_success' => 12,
            'failed_escaped' => 6,
            default => 4,
        };
        $reputation = match ($outcome) {
            'critical_success' => 3,
            'success' => 2,
            'partial_success' => 1,
            'failed_injury', 'failed_arrest', 'police_trap' => -1,
            default => 0,
        };
        $narrative = $this->narrative()->outcomeText($outcome);
        $consequences = $this->applyCrewConsequences($runId, $outcome);

        $result = [
            'outcome' => $outcome,
            'title' => $narrative['title'],
            'description' => $narrative['description'],
            'roll' => $roll,
            'decision_code' => $decisionCode,
            'crew_consequences' => $consequences,
            'source_quality' => $opportunity['quality'],
        ];

        $pdo = Database::pdo();
        $pdo->prepare(
            <<<'SQL'
                UPDATE users
                SET dirty_money = dirty_money + ?,
                    heat = heat + ?,
                    reputation = GREATEST(0, reputation + ?),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$baseReward, $heat, $reputation, $run['user_id']]);

        $this->experience->grantPlayer(
            (int) $run['user_id'],
            $experience,
            'crime_v04',
            $runId,
            'Crime opportunity outcome: ' . $outcome
        );

        $pdo->prepare(
            <<<'SQL'
                UPDATE crime_runs
                SET status = 'resolved', completed_at = NOW(), outcome = ?,
                    selected_decision_code = ?, reward_dirty_cash = ?, heat_gained = ?,
                    experience_gained = ?, reputation_gained = ?, result = ?, resolved = 1,
                    updated_at = NOW()
                WHERE id = ? AND resolved = 0
            SQL
        )->execute([
            $outcome,
            $decisionCode,
            $baseReward,
            $heat,
            $experience,
            $reputation,
            json_encode($result, JSON_THROW_ON_ERROR),
            $runId,
        ]);

        $pdo->prepare("UPDATE crime_opportunities SET status = 'completed', updated_at = NOW() WHERE id = ?")
            ->execute([$run['opportunity_id']]);

        $crewAssignments = Database::pdo()->prepare(
            <<<'SQL'
                SELECT gang_member_id, role_code
                FROM crime_run_assignments
                WHERE run_id = ?
                ORDER BY id
            SQL
        );
        $crewAssignments->execute([$runId]);

        foreach ($crewAssignments->fetchAll() as $assignment) {
            $this->experience->grantCrew(
                (int) $run['user_id'],
                (int) $assignment['gang_member_id'],
                $experience,
                'crime_v04',
                $runId,
                'Participated in crime opportunity as ' . (string) $assignment['role_code']
            );
        }

        if (!empty($opportunity['source_npc_id'])) {
            $relationshipChange = in_array($outcome, ['success', 'critical_success'], true) ? 4 : -3;
            $this->adjustRelationship((int) $run['user_id'], (int) $opportunity['source_npc_id'], $relationshipChange, $outcome);
            Database::pdo()->prepare(
                <<<'SQL'
                    INSERT INTO crime_npc_involvement (
                        run_id, npc_id, involvement_type, relationship_change, notes, created_at
                    ) VALUES (?, ?, 'source_contact', ?, ?, NOW())
                SQL
            )->execute([
                $runId,
                (int) $opportunity['source_npc_id'],
                $relationshipChange,
                'Remembered outcome: ' . $outcome,
            ]);
            $this->timeline(
                (int) $opportunity['source_npc_id'],
                (int) $run['user_id'],
                $runId,
                'crime_involvement',
                'Involved in crime opportunity',
                'This NPC was tied to a player crime opportunity and now remembers the result.',
                ['outcome' => $outcome, 'relationship_change' => $relationshipChange]
            );
        }

        AuditService::log((int) $run['user_id'], 'crime.resolve', [
            'run_id' => $runId,
            'outcome' => $outcome,
            'reward_dirty_cash' => $baseReward,
            'heat' => $heat,
        ]);

        return $this->run((int) $run['user_id'], $runId);
    }

    /** @return array<string, int> */
    private function decisionAdjustments(?string $decisionCode): array
    {
        return match ($decisionCode) {
            'abandon_loot', 'leave_now', 'retreat', 'abort' => [
                'success' => 8,
                'disaster' => -6,
                'police' => -8,
                'heat' => -2,
                'loot' => -35,
            ],
            'hide_wait' => ['success' => 3, 'disaster' => -2, 'police' => -4, 'heat' => 1, 'loot' => -10],
            'bribe_witness', 'split_score', 'accept_discount' => ['success' => 6, 'disaster' => -5, 'police' => -5, 'heat' => -2, 'loot' => -20],
            'push_through', 'stand_ground', 'pressure_buyer' => ['success' => -4, 'disaster' => 6, 'police' => 7, 'heat' => 5, 'loot' => 10],
            'take_extra' => ['success' => -6, 'disaster' => 6, 'police' => 7, 'heat' => 4, 'loot' => 25],
            'assign_carry', 'use_backup' => ['success' => 5, 'disaster' => -3, 'police' => 0, 'heat' => 1, 'loot' => 10],
            default => ['success' => 0, 'disaster' => 0, 'police' => 0, 'heat' => 0, 'loot' => 0],
        };
    }

    private function outcomeFromRoll(int $roll, int $successChance, int $disasterChance, string $quality): string
    {
        if ($quality === 'trap' && $roll <= min(45, $disasterChance + 18)) {
            return 'police_trap';
        }

        if ($roll <= max(4, (int) floor($successChance / 5))) {
            return 'critical_success';
        }

        if ($roll <= $successChance) {
            return 'success';
        }

        if ($roll <= $successChance + 18) {
            return 'partial_success';
        }

        if ($roll >= 101 - $disasterChance) {
            return $this->random->integer(1, 100) <= 50 ? 'failed_injury' : 'failed_arrest';
        }

        return 'failed_escaped';
    }

    private function rewardForOutcome(array $template, string $outcome, int $lootAdjust): int
    {
        $reward = $this->random->integer((int) $template['base_reward_min'], (int) $template['base_reward_max']);
        $modifier = match ($outcome) {
            'critical_success' => 1.25,
            'success' => 1.0,
            'partial_success' => 0.55,
            default => 0.0,
        };

        $modifier += $lootAdjust / 100;

        return max(0, (int) round($reward * $modifier));
    }

    private function heatForOutcome(array $template, string $outcome, int $policeChance, int $heatAdjust): int
    {
        $heat = $this->random->integer((int) $template['base_heat_min'], (int) $template['base_heat_max']);
        $heat += (int) floor($policeChance / 20) + $heatAdjust;

        if (in_array($outcome, ['failed_injury', 'failed_arrest', 'police_trap'], true)) {
            $heat += 8;
        } elseif ($outcome === 'critical_success') {
            $heat -= 2;
        }

        return max(0, $heat);
    }

    /** @return array<int, array<string, mixed>> */
    private function applyCrewConsequences(int $runId, string $outcome): array
    {
        if (!in_array($outcome, ['failed_injury', 'failed_arrest'], true)) {
            return [];
        }

        $assignments = Database::pdo()->prepare(
            <<<'SQL'
                SELECT assignment.gang_member_id, npc.first_name, npc.last_name
                FROM crime_run_assignments assignment
                JOIN player_gang_members member ON member.id = assignment.gang_member_id
                JOIN npcs npc ON npc.id = member.npc_id
                WHERE assignment.run_id = ?
                ORDER BY assignment.id
            SQL
        );
        $assignments->execute([$runId]);
        $rows = $assignments->fetchAll();

        if ($rows === []) {
            return [];
        }

        $selected = $rows[$this->random->integer(0, count($rows) - 1)];
        $status = $outcome === 'failed_arrest' ? 'arrested' : 'injured';
        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE player_gang_members
                SET status = ?, current_assignment_type = NULL, current_assignment_id = NULL,
                    recovery_until = CASE WHEN ? = 'injured' THEN DATE_ADD(NOW(), INTERVAL 2 DAY) ELSE recovery_until END,
                    arrested_until = CASE WHEN ? = 'arrested' THEN DATE_ADD(NOW(), INTERVAL 2 DAY) ELSE arrested_until END,
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$status, $status, $status, $selected['gang_member_id']]);

        return [[
            'gang_member_id' => (int) $selected['gang_member_id'],
            'name' => trim($selected['first_name'] . ' ' . $selected['last_name']),
            'status' => $status,
        ]];
    }

    private function createRunEvent(int $runId, array $event): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO crime_events (run_id, event_code, title, description, choices, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            $runId,
            $event['code'],
            $event['title'],
            $event['description'],
            json_encode($event['choices'], JSON_THROW_ON_ERROR),
        ]);
    }

    private function chooseEventCode(array $template, array $risk, int $eventRoll): string
    {
        $possible = $this->decodeJson($template['possible_events']);

        if ($eventRoll <= $risk['police_chance']) {
            return 'police_patrol';
        }

        if ($possible === []) {
            return 'witness_spotted';
        }

        return (string) $possible[$this->random->integer(0, count($possible) - 1)];
    }

    /** @return array<string, mixed>|null */
    private function pendingEvent(int $runId): ?array
    {
        $statement = Database::pdo()->prepare(
            "SELECT * FROM crime_events WHERE run_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1"
        );
        $statement->execute([$runId]);
        $event = $statement->fetch();

        return $event ?: null;
    }

    /** @return array<string, mixed> */
    private function hydrateRun(array $run): array
    {
        $run['result'] = $this->decodeJson($run['result']);
        $event = $this->pendingEvent((int) $run['id']);
        if ($event) {
            $event['choices'] = $this->decodeJson($event['choices']);
        }
        $run['event'] = $event;

        return $run;
    }

    /** @return array<string, mixed> */
    private function formatOpportunity(array $row): array
    {
        $row['recommended_roles'] = $this->decodeJson($row['recommended_roles'] ?? '[]');
        $row['required_items'] = $this->decodeJson($row['required_items'] ?? '[]');
        $row['relevant_stats'] = $this->decodeJson($row['relevant_stats'] ?? '[]');
        $row['metadata'] = $this->decodeJson($row['metadata'] ?? '{}');
        $row['preparations'] = $this->preparations((int) $row['id']);
        $row['assignments'] = $this->opportunityAssignments((int) $row['id']);
        $row['equipment'] = $this->opportunityEquipment((int) $row['id']);
        $row['preparation_options'] = $this->narrative()->preparationOptions();
        $row['source_name'] = $this->npcName([
            'first_name' => $row['source_first_name'] ?? '',
            'last_name' => $row['source_last_name'] ?? '',
            'nickname' => $row['source_nickname'] ?? null,
        ]);
        $row['is_expired'] = $this->isExpired($row);
        $row['can_investigate'] = in_array($row['information_level'], ['rumor', 'lead'], true)
            && !in_array($row['status'], ['completed', 'abandoned', 'expired'], true);
        $row['can_prepare'] = in_array($row['information_level'], ['confirmed', 'trap'], true)
            && !in_array($row['status'], ['completed', 'abandoned', 'expired', 'active'], true);
        $row['can_execute'] = in_array($row['information_level'], ['confirmed', 'trap'], true)
            && !in_array($row['status'], ['completed', 'abandoned', 'expired', 'active'], true);

        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    private function preparations(int $opportunityId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM crime_preparations WHERE opportunity_id = ? ORDER BY id'
        );
        $statement->execute([$opportunityId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['effects'] = $this->decodeJson($row['effects']);
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function opportunityAssignments(int $opportunityId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT assignment.*, member.status, member.strength, member.shooting, member.driving,
                       member.intelligence, member.stealth, member.intimidation,
                       member.discipline, member.street_knowledge, member.endurance,
                       member.loyalty, member.morale, npc.first_name, npc.last_name, npc.nickname
                FROM crime_opportunity_assignments assignment
                JOIN player_gang_members member ON member.id = assignment.gang_member_id
                JOIN npcs npc ON npc.id = member.npc_id
                WHERE assignment.opportunity_id = ?
                ORDER BY assignment.id
            SQL
        );
        $statement->execute([$opportunityId]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    private function opportunityEquipment(int $opportunityId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT selected.*, item.name AS item_name, item.category AS item_category,
                       item.effects AS item_effects, weapon.name AS weapon_name,
                       weapon.class AS weapon_category, weapon.effects AS weapon_effects
                FROM crime_opportunity_equipment selected
                LEFT JOIN item_definitions item
                    ON item.id = selected.asset_id AND selected.asset_type = 'item'
                LEFT JOIN weapons weapon
                    ON weapon.id = selected.asset_id AND selected.asset_type = 'weapon'
                WHERE selected.opportunity_id = ?
                ORDER BY selected.id
            SQL
        );
        $statement->execute([$opportunityId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['name'] = $row['asset_type'] === 'item' ? $row['item_name'] : $row['weapon_name'];
            $row['category'] = $row['asset_type'] === 'item' ? $row['item_category'] : $row['weapon_category'];
            $row['effects'] = $this->decodeJson($row['asset_type'] === 'item' ? $row['item_effects'] : $row['weapon_effects']);
        }

        return $rows;
    }

    private function copyAssignmentsToRun(int $runId, array $assignments): void
    {
        $insert = Database::pdo()->prepare(
            'INSERT INTO crime_run_assignments (run_id, gang_member_id, role_code, created_at) VALUES (?, ?, ?, NOW())'
        );

        foreach ($assignments as $assignment) {
            $insert->execute([$runId, $assignment['gang_member_id'], $assignment['role_code']]);
        }
    }

    private function copyEquipmentToRun(int $runId, array $equipment): void
    {
        $insert = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO crime_run_equipment (run_id, asset_type, asset_id, name, quantity, effects, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            SQL
        );

        foreach ($equipment as $entry) {
            $insert->execute([
                $runId,
                $entry['asset_type'],
                $entry['asset_id'],
                $entry['name'] ?? 'Unknown',
                $entry['quantity'],
                json_encode($entry['effects'] ?? [], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function loadOpportunity(int $userId, int $opportunityId): ?array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    opportunity.*,
                    template.code,
                    template.category,
                    template.tier,
                    template.briefing,
                    template.energy_cost,
                    template.base_success_rate,
                    template.base_disaster_chance,
                    template.min_crew,
                    template.max_crew,
                    template.recommended_roles,
                    template.required_items,
                    template.relevant_stats,
                    location.name AS location_name,
                    territory.name AS territory_name,
                    territory.police_presence,
                    npc.first_name AS source_first_name,
                    npc.last_name AS source_last_name,
                    npc.nickname AS source_nickname,
                    npc.role AS source_role,
                    npc.alive AS source_alive
                FROM crime_opportunities opportunity
                JOIN crime_v04_templates template ON template.id = opportunity.template_id
                JOIN crime_discovery_locations location ON location.id = opportunity.location_id
                LEFT JOIN territories territory ON territory.id = opportunity.territory_id
                LEFT JOIN npcs npc ON npc.id = opportunity.source_npc_id
                WHERE opportunity.id = ? AND opportunity.user_id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$opportunityId, $userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function lockOpportunity(int $userId, int $opportunityId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM crime_opportunities WHERE id = ? AND user_id = ? FOR UPDATE'
        );
        $statement->execute([$opportunityId, $userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function lockRun(int $userId, int $runId): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM crime_runs WHERE id = ? AND user_id = ? FOR UPDATE');
        $statement->execute([$runId, $userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function lockRunById(int $runId): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM crime_runs WHERE id = ? FOR UPDATE');
        $statement->execute([$runId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function template(int $templateId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM crime_v04_templates WHERE id = ? LIMIT 1');
        $statement->execute([$templateId]);
        $template = $statement->fetch();

        if (!$template) {
            throw new RuntimeException('Crime template not found.');
        }

        return $template;
    }

    /** @return array<string, mixed> */
    private function chooseTemplateForLocation(string $locationCode): array
    {
        $categoryHints = match ($locationCode) {
            'bar' => ['business-targeted crime', 'smuggling'],
            'street' => ['pickpocketing', 'fencing stolen goods'],
            'garage' => ['vehicle theft'],
            'pawn_shop' => ['fencing stolen goods', 'business-targeted crime'],
            'warehouse_district' => ['burglary', 'smuggling'],
            default => [],
        };

        $sql = 'SELECT * FROM crime_v04_templates WHERE active = 1';
        $params = [];

        if ($categoryHints !== []) {
            $placeholders = implode(',', array_fill(0, count($categoryHints), '?'));
            $sql .= " AND category IN ({$placeholders})";
            $params = $categoryHints;
        }

        $sql .= ' ORDER BY tier, id';
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);
        $templates = $statement->fetchAll();

        if ($templates === []) {
            $templates = Database::pdo()
                ->query('SELECT * FROM crime_v04_templates WHERE active = 1 ORDER BY tier, id')
                ->fetchAll();
        }

        if ($templates === []) {
            throw new RuntimeException('No crime templates are seeded.');
        }

        return $templates[$this->random->integer(0, count($templates) - 1)];
    }

    /** @return array<string, mixed> */
    private function chooseTerritory(): array
    {
        $territories = Database::pdo()->query('SELECT * FROM territories ORDER BY id')->fetchAll();

        if ($territories === []) {
            return ['id' => null, 'name' => 'Unknown district', 'police_presence' => 45];
        }

        return $territories[$this->random->integer(0, count($territories) - 1)];
    }

    /** @return array<string, mixed>|null */
    private function chooseSourceNpc(string $locationCode, int $userId): ?array
    {
        $roleHints = match ($locationCode) {
            'bar' => ['bartender', 'informant'],
            'garage' => ['mechanic'],
            'pawn_shop' => ['fence'],
            'warehouse_district' => ['warehouse_worker', 'informant'],
            default => ['informant', 'civilian'],
        };

        $placeholders = implode(',', array_fill(0, count($roleHints), '?'));
        $statement = Database::pdo()->prepare(
            "SELECT * FROM npcs WHERE alive = 1 AND status <> 'dead' AND role IN ({$placeholders}) ORDER BY RAND() LIMIT 1"
        );
        $statement->execute($roleHints);
        $npc = $statement->fetch();

        if ($npc) {
            return $npc;
        }

        return $this->createGeneratedNpc($locationCode, $userId);
    }

    /** @return array<string, mixed> */
    private function createGeneratedNpc(string $locationCode, int $userId): array
    {
        $profiles = [
            'bar' => ['first' => 'Nika', 'last' => 'Moss', 'nick' => 'Low Light', 'role' => 'informant', 'occupation' => 'Bar regular', 'gender' => 'female'],
            'street' => ['first' => 'Aron', 'last' => 'Pike', 'nick' => 'Corner', 'role' => 'civilian', 'occupation' => 'Street contact', 'gender' => 'male'],
            'garage' => ['first' => 'Dima', 'last' => 'North', 'nick' => 'Ratchet', 'role' => 'mechanic', 'occupation' => 'Mechanic', 'gender' => 'male'],
            'pawn_shop' => ['first' => 'Lena', 'last' => 'Vale', 'nick' => 'Receipt', 'role' => 'fence', 'occupation' => 'Pawn shop broker', 'gender' => 'female'],
            'warehouse_district' => ['first' => 'Mark', 'last' => 'Kell', 'nick' => 'Night Shift', 'role' => 'warehouse_worker', 'occupation' => 'Warehouse clerk', 'gender' => 'male'],
        ];
        $profile = $profiles[$locationCode] ?? $profiles['street'];

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO npcs (
                    first_name, last_name, nickname, age, gender, biography, background,
                    occupation, role, affiliation, current_activity, personal_cash,
                    wealth_class, status, alive, is_contact, is_informant,
                    reliability, courage, greed, source_event, met_player_at,
                    last_seen_at, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'player contact', ?, ?, 'working', 'active', 1, 1, 1, ?, ?, ?, 'crime_discovery', NOW(), NOW(), NOW(), NOW())
            SQL
        )->execute([
            $profile['first'],
            $profile['last'],
            $profile['nick'],
            $this->random->integer(23, 55),
            $profile['gender'],
            'A generated persistent NPC met through the v0.4 crime discovery loop.',
            'This character can reappear in later crime hooks.',
            $profile['occupation'],
            $profile['role'],
            'Watching for opportunities.',
            $this->random->integer(40, 240),
            $this->random->integer(42, 72),
            $this->random->integer(35, 65),
            $this->random->integer(30, 70),
        ]);

        $npcId = (int) Database::pdo()->lastInsertId();
        try {
            (new PortraitAssignmentService())->assignToNpc($npcId);
        } catch (Throwable) {
            // Portrait assignment can fail on unsupported gender data. The UI still has a fallback.
        }

        $statement = Database::pdo()->prepare('SELECT * FROM npcs WHERE id = ? LIMIT 1');
        $statement->execute([$npcId]);
        return $statement->fetch();
    }

    private function qualityFromRoll(int $roll, int $trust, int $heat): string
    {
        $adjusted = $roll + (int) floor(($trust - 50) / 3) - (int) floor(max(0, $heat - 50) / 10);

        if ($adjusted <= 6) {
            return 'trap';
        }
        if ($adjusted <= 22) {
            return 'suspicious';
        }
        if ($adjusted <= 48) {
            return 'weak';
        }
        if ($adjusted >= 84) {
            return 'strong';
        }

        return 'normal';
    }

    private function informationLevelFromQuality(string $quality, int $roll): string
    {
        return match ($quality) {
            'trap' => 'trap',
            'suspicious' => $roll % 2 === 0 ? 'rumor' : 'lead',
            'weak' => 'rumor',
            'strong' => 'confirmed',
            default => 'lead',
        };
    }

    private function locationByCode(string $code): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM crime_discovery_locations WHERE code = ? AND active = 1 LIMIT 1');
        $statement->execute([$code]);
        $location = $statement->fetch();

        return $location ?: null;
    }

    private function lockUser(int $userId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        return $user;
    }

    private function crewMember(int $userId, int $memberId): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM player_gang_members WHERE id = ? AND user_id = ? LIMIT 1');
        $statement->execute([$memberId, $userId]);
        $member = $statement->fetch();

        return $member ?: null;
    }

    private function validateOwnedAsset(int $userId, string $assetType, int $assetId, int $quantity): void
    {
        if ($assetType === 'item') {
            $statement = Database::pdo()->prepare('SELECT quantity FROM user_items WHERE user_id = ? AND item_definition_id = ? LIMIT 1');
        } else {
            $statement = Database::pdo()->prepare('SELECT quantity FROM user_weapons WHERE user_id = ? AND weapon_id = ? LIMIT 1');
        }

        $statement->execute([$userId, $assetId]);
        $row = $statement->fetch();

        if (!$row || (int) $row['quantity'] < $quantity) {
            throw new RuntimeException('You do not own enough of that equipment.');
        }
    }

    private function preparationOption(string $code): array
    {
        foreach ($this->narrative()->preparationOptions() as $option) {
            if ($option['code'] === $code) {
                return $option;
            }
        }

        throw new RuntimeException('Unknown preparation option.');
    }

    private function ensureRelationship(int $userId, int $npcId, string $type): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM npc_relationships WHERE user_id = ? AND npc_id = ? LIMIT 1'
        );
        $statement->execute([$userId, $npcId]);
        $relationship = $statement->fetch();

        if ($relationship) {
            return $relationship;
        }

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO npc_relationships (
                    user_id, npc_id, relationship_type, trust, respect, suspicion,
                    known_player_identity, notes, created_at, updated_at
                ) VALUES (?, ?, ?, 20, 5, 10, 1, 'Met through crime discovery.', NOW(), NOW())
            SQL
        )->execute([$userId, $npcId, $type]);

        $statement->execute([$userId, $npcId]);
        return $statement->fetch();
    }

    private function adjustRelationship(int $userId, int $npcId, int $trustDelta, string $outcome): void
    {
        $this->ensureRelationship($userId, $npcId, 'crime contact');
        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE npc_relationships
                SET trust = LEAST(100, GREATEST(-100, trust + ?)),
                    respect = LEAST(100, GREATEST(-100, respect + ?)),
                    suspicion = LEAST(100, GREATEST(0, suspicion + ?)),
                    notes = ?,
                    updated_at = NOW()
                WHERE user_id = ? AND npc_id = ?
            SQL
        )->execute([
            $trustDelta,
            $trustDelta > 0 ? 2 : -1,
            $trustDelta < 0 ? 4 : -1,
            "Last remembered crime outcome: {$outcome}",
            $userId,
            $npcId,
        ]);
    }

    private function touchNpc(int $npcId, string $activity): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE npcs
                SET is_contact = 1, met_player_at = COALESCE(met_player_at, NOW()),
                    last_seen_at = NOW(), current_activity = ?, updated_at = NOW()
                WHERE id = ? AND alive = 1
            SQL
        )->execute([$activity, $npcId]);
    }

    private function timeline(
        int $npcId,
        ?int $userId,
        ?int $runId,
        string $eventType,
        string $title,
        string $description,
        array $metadata = []
    ): void {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO npc_timeline_events (
                    npc_id, user_id, crime_run_id, event_type, title, description, metadata, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            $npcId,
            $userId,
            $runId,
            $eventType,
            $title,
            $description,
            json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function isExpired(array $opportunity): bool
    {
        return !empty($opportunity['expires_at']) && strtotime((string) $opportunity['expires_at']) < time();
    }

    private function expireOpportunity(int $opportunityId): void
    {
        Database::pdo()->prepare("UPDATE crime_opportunities SET status = 'expired', updated_at = NOW() WHERE id = ?")
            ->execute([$opportunityId]);
    }

    private function opportunityTitle(array $template, string $informationLevel): string
    {
        return match ($informationLevel) {
            'rumor' => 'Rumor: ' . $template['title'],
            'lead' => 'Lead: ' . $template['title'],
            'trap' => 'Suspicious lead: ' . $template['title'],
            default => $template['title'],
        };
    }

    private function targetName(array $template, array $territory): string
    {
        return ($territory['name'] ?? 'Unknown district') . ' · ' . $template['category'];
    }

    private function sourceDescription(array $location, ?array $npc, string $quality): string
    {
        $source = $npc ? $this->npcName($npc) : $location['name'];
        return "{$source} supplied a {$quality} piece of information. Details remain abstract and game-like.";
    }

    private function sourceType(string $locationCode): string
    {
        return match ($locationCode) {
            'bar' => 'bar rumor',
            'garage' => 'mechanic tip',
            'pawn_shop' => 'fence tip',
            'warehouse_district' => 'worker leak',
            default => 'street observation',
        };
    }

    private function sourceRelationshipType(array $npc): string
    {
        if ((int) ($npc['is_rival'] ?? 0) === 1) {
            return 'rival';
        }
        if ((int) ($npc['is_informant'] ?? 0) === 1) {
            return 'informant';
        }
        if ((int) ($npc['is_contact'] ?? 0) === 1) {
            return 'contact';
        }

        return 'known npc';
    }

    private function locationBlockedReason(array $user, array $location): ?string
    {
        if ((int) $user['level'] < (int) $location['min_level']) {
            return 'Requires level ' . $location['min_level'];
        }
        if ((int) $user['energy'] < (int) $location['energy_cost']) {
            return 'Not enough energy';
        }
        if ((int) $user['cash'] < (int) $location['cash_cost']) {
            return 'Not enough cash';
        }

        return null;
    }

    private function npcName(array $npc): string
    {
        $name = trim((string) ($npc['first_name'] ?? '') . ' ' . (string) ($npc['last_name'] ?? ''));
        $nickname = trim((string) ($npc['nickname'] ?? ''));

        if ($nickname !== '') {
            return $nickname;
        }

        return $name !== '' ? $name : 'Unknown NPC';
    }

    private function riskCalculator(): CrimeRiskCalculator
    {
        return $this->riskCalculator ?? new CrimeRiskCalculator();
    }

    private function narrative(): CrimeNarrativeService
    {
        return $this->narrative ?? new CrimeNarrativeService();
    }

    /** @return array<string, mixed> */
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
