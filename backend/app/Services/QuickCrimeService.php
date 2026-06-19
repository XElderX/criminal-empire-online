<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class QuickCrimeService
{
    private ItemRequirementService $items;
    private ExperienceService $experience;
    private SkillProgressionService $skills;

    public function __construct(
        private readonly RandomSource $random = new SecureRandomSource()
    ) {
        $this->items = new ItemRequirementService();
        $this->experience = new ExperienceService();
        $this->skills = new SkillProgressionService();
    }

    public function list(array $user, array $filters = []): array
    {
        $context = null;
        $params = [];
        $sql = <<<'SQL'
                SELECT template.*, rule.requires_current_location,
                       rule.reward_multiplier AS local_reward_multiplier,
                       rule.heat_multiplier AS local_heat_multiplier,
                       rule.police_risk_multiplier AS local_police_risk_multiplier,
                       rule.danger_multiplier AS local_danger_multiplier,
                       COALESCE(rule.sort_order, 999) AS local_sort_order,
                       region.slug AS local_region_slug,
                       region.name AS local_region_name,
                       location.slug AS local_location_slug,
                       location.name AS local_location_name
                FROM quick_crime_templates template
                LEFT JOIN quick_crime_location_rules rule ON rule.quick_crime_template_id = template.id
                LEFT JOIN world_regions region ON region.id = rule.world_region_id
                LEFT JOIN world_locations location ON location.id = rule.world_location_id
                WHERE template.active = 1
            SQL;

        if (!empty($filters['region']) || !empty($filters['location'])) {
            $context = (new MapContextService())->resolve(
                $user,
                isset($filters['region']) ? (string) $filters['region'] : null,
                isset($filters['location']) ? (string) $filters['location'] : null
            );
            $sql .= ' AND rule.is_allowed = 1 AND (rule.world_location_id = ? OR (rule.world_location_id IS NULL AND rule.world_region_id = ?))';
            $params[] = $context['location']['id'];
            $params[] = $context['region']['id'];
        }

        $sql .= ' ORDER BY COALESCE(rule.sort_order, 999), template.tier, template.min_level, template.id';

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);
        $templates = $statement->fetchAll();
        $inventory = $this->items->inventoryForUser((int) $user['id']);

        $data = [];
        $seenTemplateIds = [];
        foreach ($templates as $template) {
            $templateId = (int) $template['id'];
            if (isset($seenTemplateIds[$templateId])) {
                continue;
            }

            $seenTemplateIds[$templateId] = true;
            $data[] = $this->formatTemplate($template, $user, $inventory, $context);
        }

        return [
            'data' => $data,
            'active_runs' => $this->activeRuns((int) $user['id']),
            'history' => $this->history((int) $user['id'], 12),
            'progression' => $this->progression((int) $user['id']),
            'locationContext' => $context,
            'filters' => $filters,
        ];
    }

    public function show(array $user, int $templateId): array
    {
        $template = $this->findTemplate($templateId);

        if (!$template) {
            throw new RuntimeException('Quick crime not found.');
        }

        return [
            'quick_crime' => $this->formatTemplate(
                $template,
                $user,
                $this->items->inventoryForUser((int) $user['id'])
            ),
        ];
    }

    public function prepare(array $user, int $templateId, string $code): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $template = $this->lockTemplate($templateId);
            if (!$template) {
                throw new RuntimeException('Quick crime not found.');
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $option = $this->preparationOption($template, $code);

            if (!$option) {
                throw new RuntimeException('Preparation option is not available.');
            }

            $existing = $pdo->prepare(
                'SELECT id FROM quick_crime_preparations WHERE user_id = ? AND template_id = ? AND code = ? LIMIT 1'
            );
            $existing->execute([$freshUser['id'], $template['id'], $code]);
            if ($existing->fetch()) {
                throw new RuntimeException('This preparation was already applied.');
            }

            $cashCost = (int) ($option['cash_cost'] ?? 0);
            $energyCost = (int) ($option['energy_cost'] ?? 0);

            if ((int) $freshUser['cash'] < $cashCost) {
                throw new RuntimeException('Not enough cash for this preparation.');
            }

            if ((int) $freshUser['energy'] < $energyCost) {
                throw new RuntimeException('Not enough energy for this preparation.');
            }

            $pdo->prepare(
                'UPDATE users SET cash = cash - ?, energy = energy - ?, updated_at = NOW() WHERE id = ?'
            )->execute([$cashCost, $energyCost, $freshUser['id']]);

            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO quick_crime_preparations (
                        user_id,
                        template_id,
                        code,
                        name,
                        description,
                        cash_cost,
                        energy_cost,
                        effects,
                        expires_at,
                        applied_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW())
                SQL
            )->execute([
                $freshUser['id'],
                $template['id'],
                $code,
                $option['name'] ?? $code,
                $option['description'] ?? '',
                $cashCost,
                $energyCost,
                json_encode($option['effects'] ?? [], JSON_THROW_ON_ERROR),
            ]);

            if ($energyCost > 0) {
                $this->experience->grantPlayer(
                    (int) $freshUser['id'],
                    1,
                    'quick_crime_preparation',
                    (int) $template['id'],
                    'Prepared for a quick street action.'
                );
            }

            $pdo->commit();

            return [
                'message' => 'Quick crime preparation applied.',
                'preparation' => $option,
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function start(array $user, int $templateId, array $payload = []): array
    {
        $idempotencyKey = $this->normalizeIdempotencyKey((string) ($payload['idempotency_key'] ?? ''));
        $crewIds = array_values(array_unique(array_map('intval', is_array($payload['crew_ids'] ?? null) ? $payload['crew_ids'] : [])));
        $equipment = is_array($payload['equipment'] ?? null) ? $payload['equipment'] : [];
        $districtCode = $payload['district_code'] ?? null;
        $targetKey = $payload['target_key'] ?? null;
        $regionSlug = isset($payload['region_slug']) ? (string) $payload['region_slug'] : null;
        $locationSlug = isset($payload['location_slug']) ? (string) $payload['location_slug'] : null;

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $existing = $this->runByIdempotency((int) $user['id'], $idempotencyKey);
            if ($existing) {
                $pdo->commit();
                return [
                    'message' => 'Quick crime request was already processed.',
                    'run' => $this->hydrateRun($existing),
                ];
            }

            $template = $this->lockTemplate($templateId);
            if (!$template) {
                throw new RuntimeException('Quick crime not found.');
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $localContext = null;
            $localRule = null;
            if ($regionSlug !== null || $locationSlug !== null) {
                $localContext = (new MapContextService())->resolve($freshUser, $regionSlug, $locationSlug);
                $localRule = $this->locationRuleForTemplate((int) $template['id'], $localContext);
                if (!$localRule) {
                    throw new RuntimeException('This quick crime is not available at the selected location.');
                }
                if ((int) $localRule['requires_current_location'] === 1 && !$localContext['playerIsHere']) {
                    throw new RuntimeException('Travel to ' . $localContext['location']['name'] . ' before starting this local quick crime.');
                }
                $districtCode = $localContext['region']['slug'];
                $targetKey = $localContext['location']['slug'];
            }
            $inventory = $this->items->inventoryForUser((int) $freshUser['id']);
            $validation = $this->requirementMessages($template, $freshUser, $inventory);

            if ($validation !== []) {
                throw new RuntimeException(implode(' ', $validation));
            }

            $this->validateCooldowns((int) $freshUser['id'], $template);
            $crew = $this->validateCrew((int) $freshUser['id'], $crewIds, (int) $template['required_crew_count']);
            $this->validateEquipmentSelection($equipment, $inventory);

            if ((int) $freshUser['energy'] < (int) $template['energy_cost']) {
                throw new RuntimeException('Not enough energy.');
            }

            $preparations = $this->loadPreparations((int) $freshUser['id'], (int) $template['id']);
            $effects = $this->aggregateEffects($preparations);
            $equipmentEffects = $this->items->effectsForSelection((int) $freshUser['id'], $equipment);

            foreach ($equipmentEffects as $effect => $value) {
                $effects[$effect] = ($effects[$effect] ?? 0) + $value;
            }
            if ($localContext !== null) {
                $locationModifiers = (new LocationRiskModifierService())->forLocation(
                    $localContext['location'],
                    $localContext['territory'],
                    $localRule
                );
                $effects['reward_multiplier'] = (float) $locationModifiers['reward_multiplier'];
                $effects['heat_multiplier'] = (float) $locationModifiers['heat_multiplier'];
                $effects['police_risk_multiplier'] = (float) $locationModifiers['police_risk_multiplier'];
                $effects['danger_multiplier'] = (float) $locationModifiers['danger_multiplier'];
                $effects['location_context'] = [
                    'region_slug' => $localContext['region']['slug'],
                    'region_name' => $localContext['region']['name'],
                    'location_slug' => $localContext['location']['slug'],
                    'location_name' => $localContext['location']['name'],
                    'territory_effect' => $locationModifiers['territory_effect'],
                    'riskSummary' => $localContext['riskSummary'],
                ];
            }

            $successChance = $this->successChance($template, $freshUser, $crew, $effects);
            $eventChance = $this->eventChance($template, $freshUser, $effects);
            $disasterChance = $this->disasterChance($template, $freshUser, $effects);
            $eventRoll = $this->random->integer(1, 100);

            $pdo->prepare(
                'UPDATE users SET energy = energy - ?, updated_at = NOW() WHERE id = ?'
            )->execute([(int) $template['energy_cost'], $freshUser['id']]);

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO quick_crime_runs (
                        user_id,
                        template_id,
                        idempotency_key,
                        status,
                        district_code,
                        target_key,
                        started_at,
                        success_chance,
                        event_chance,
                        disaster_chance,
                        result,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, 'active', ?, ?, NOW(), ?, ?, ?, ?, NOW(), NOW())
                SQL
            );
            $insert->execute([
                $freshUser['id'],
                $template['id'],
                $idempotencyKey,
                $districtCode,
                $targetKey,
                $successChance,
                $eventChance,
                $disasterChance,
                json_encode([
                    'preparations' => array_column($preparations, 'code'),
                    'selected_equipment' => $equipment,
                    'crew_ids' => $crewIds,
                    'effects' => $effects,
                ], JSON_THROW_ON_ERROR),
            ]);

            $runId = (int) $pdo->lastInsertId();
            $this->storeRunCrew((int) $freshUser['id'], $runId, $crew);
            $this->storeRunEquipment((int) $freshUser['id'], $runId, $equipment, $inventory);

            if ($eventRoll <= $eventChance && $this->eventPool($template) !== []) {
                $event = $this->createEvent($runId, $template);
                $pdo->prepare(
                    'UPDATE quick_crime_runs SET status = ?, updated_at = NOW() WHERE id = ?'
                )->execute(['awaiting_decision', $runId]);

                $this->startCooldowns((int) $freshUser['id'], $template, $districtCode, $targetKey);
                $run = $this->findRun((int) $freshUser['id'], $runId);

                $pdo->commit();

                return [
                    'message' => 'Quick crime started, but a street event needs a decision.',
                    'run' => $this->hydrateRun($run, $event),
                ];
            }

            $result = $this->resolveLockedRun($freshUser, $template, $runId, null, $crew, $effects, $districtCode, $targetKey);
            $pdo->commit();

            return [
                'message' => 'Quick crime resolved.',
                'run' => $result,
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function decide(array $user, int $runId, string $decisionCode): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRun((int) $user['id'], $runId);
            if (!$run) {
                throw new RuntimeException('Quick crime run not found.');
            }

            if ((int) $run['resolved'] === 1) {
                $pdo->commit();
                return [
                    'message' => 'Quick crime was already resolved.',
                    'run' => $this->hydrateRun($run),
                ];
            }

            $event = $this->pendingEvent($runId);
            if (!$event) {
                throw new RuntimeException('No pending decision event exists for this run.');
            }

            $choices = $this->decodeJson($event['choices']);
            $choice = null;
            foreach ($choices as $entry) {
                if (($entry['code'] ?? '') === $decisionCode) {
                    $choice = $entry;
                    break;
                }
            }

            if (!$choice) {
                throw new RuntimeException('Invalid event decision.');
            }

            $template = $this->lockTemplate((int) $run['template_id']);
            $freshUser = $this->lockUser((int) $user['id']);
            $crew = $this->crewForRun((int) $freshUser['id'], (int) $run['id']);
            $effects = $this->decodeJson($run['result'] ?? null);
            $effects = is_array($effects['effects'] ?? null) ? $effects['effects'] : [];
            $effects = array_merge($effects, is_array($choice['effects'] ?? null) ? $choice['effects'] : []);

            $pdo->prepare(
                'UPDATE quick_crime_events SET status = ?, selected_choice_code = ?, resolved_at = NOW() WHERE id = ?'
            )->execute(['resolved', $decisionCode, $event['id']]);

            $result = $this->resolveLockedRun(
                $freshUser,
                $template,
                (int) $run['id'],
                $decisionCode,
                $crew,
                $effects,
                $run['district_code'] ?: null,
                $run['target_key'] ?: null
            );

            $pdo->commit();

            return [
                'message' => 'Quick crime event resolved.',
                'run' => $result,
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function resolve(array $user, int $runId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $run = $this->lockRun((int) $user['id'], $runId);
            if (!$run) {
                throw new RuntimeException('Quick crime run not found.');
            }

            if ((int) $run['resolved'] === 1) {
                $pdo->commit();

                return [
                    'message' => 'Quick crime was already resolved.',
                    'run' => $this->hydrateRun($run),
                ];
            }

            if ($this->pendingEvent($runId)) {
                throw new RuntimeException('A pending quick crime event must be handled first.');
            }

            $template = $this->lockTemplate((int) $run['template_id']);
            $freshUser = $this->lockUser((int) $user['id']);
            $result = $this->resolveLockedRun(
                $freshUser,
                $template,
                (int) $run['id'],
                null,
                $this->crewForRun((int) $freshUser['id'], (int) $run['id']),
                [],
                $run['district_code'] ?: null,
                $run['target_key'] ?: null
            );

            $pdo->commit();

            return [
                'message' => 'Quick crime resolved.',
                'run' => $result,
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function history(int $userId, int $limit = 20): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT run.*, template.title, template.category
                FROM quick_crime_runs run
                JOIN quick_crime_templates template ON template.id = run.template_id
                WHERE run.user_id = ?
                ORDER BY run.id DESC
                LIMIT ?
            SQL
        );
        $statement->bindValue(1, $userId);
        $statement->bindValue(2, $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $run): array => $this->hydrateRun($run), $statement->fetchAll());
    }

    public function progression(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT id, level, experience, strength, intelligence, charisma, combat, leadership FROM users WHERE id = ?'
        );
        $statement->execute([$userId]);
        $player = $statement->fetch() ?: [];

        return [
            'player' => $player,
            'recent_experience' => $this->recentExperience($userId),
            'recent_skill_gains' => $this->recentSkillGains($userId),
        ];
    }

    private function formatTemplate(array $template, array $user, array $inventory, ?array $context = null): array
    {
        $requiredAll = $this->decodeJson($template['required_all_item_tags']);
        $requiredAny = $this->decodeJson($template['required_any_item_tags']);
        $recommended = $this->decodeJson($template['recommended_item_tags']);
        $lockedReasons = $this->requirementMessages($template, $user, $inventory);
        $cooldown = $this->cooldownState((int) $user['id'], $template);
        if ($cooldown['active']) {
            $lockedReasons[] = 'Cooldown active for ' . $cooldown['remaining_seconds'] . ' seconds.';
        }

        return [
            'id' => (int) $template['id'],
            'code' => $template['code'],
            'title' => $template['title'],
            'category' => $template['category'],
            'description' => $template['description'],
            'tier' => (int) $template['tier'],
            'min_level' => (int) $template['min_level'],
            'energy_cost' => (int) $template['energy_cost'],
            'max_heat' => $template['max_heat'] === null ? null : (int) $template['max_heat'],
            'cooldown_seconds' => (int) $template['cooldown_seconds'],
            'base_success_rate' => (int) $template['base_success_rate'],
            'base_event_chance' => (int) $template['base_event_chance'],
            'base_disaster_chance' => (int) $template['base_disaster_chance'],
            'reward_min' => (int) $template['reward_min'],
            'reward_max' => (int) $template['reward_max'],
            'heat_min' => (int) $template['heat_min'],
            'heat_max' => (int) $template['heat_max'],
            'xp_min' => (int) $template['xp_min'],
            'xp_max' => (int) $template['xp_max'],
            'required_all_item_tags' => $requiredAll,
            'required_any_item_tags' => $requiredAny,
            'recommended_item_tags' => $recommended,
            'required_crew_count' => (int) $template['required_crew_count'],
            'recommended_crew_roles' => $this->decodeJson($template['recommended_crew_roles']),
            'relevant_stats' => $this->decodeJson($template['relevant_stats']),
            'preparation_options' => $this->decodeJson($template['preparation_options']),
            'can_start' => $lockedReasons === [] && !$cooldown['active'],
            'locked_reasons' => $lockedReasons,
            'missing_items' => $this->missingItemsForTemplate((int) $user['id'], $requiredAll, $requiredAny),
            'cooldown' => $cooldown,
            'prepared' => $this->loadPreparations((int) $user['id'], (int) $template['id']),
            'is_local' => !empty($template['local_location_slug']) || $context !== null,
            'local_region_slug' => $template['local_region_slug'] ?? ($context['region']['slug'] ?? null),
            'local_region_name' => $template['local_region_name'] ?? ($context['region']['name'] ?? null),
            'local_location_slug' => $template['local_location_slug'] ?? ($context['location']['slug'] ?? null),
            'local_location_name' => $template['local_location_name'] ?? ($context['location']['name'] ?? null),
            'requires_current_location' => isset($template['requires_current_location']) ? (bool) $template['requires_current_location'] : false,
            'local_modifiers' => $context ? (new LocationRiskModifierService())->forLocation($context['location'], $context['territory'], $template) : null,
        ];
    }

    private function requirementMessages(array $template, array $user, array $inventory): array
    {
        $messages = [];

        if ((int) $user['level'] < (int) $template['min_level']) {
            $messages[] = 'Requires level ' . (int) $template['min_level'] . '.';
        }

        if ((int) $user['energy'] < (int) $template['energy_cost']) {
            $messages[] = 'Not enough energy.';
        }

        if ($template['max_heat'] !== null && (int) $user['heat'] > (int) $template['max_heat']) {
            $messages[] = 'Heat is too high for this action.';
        }

        $tags = $inventory['tags'] ?? [];
        foreach ($this->decodeJson($template['required_all_item_tags']) as $tag) {
            if (!isset($tags[$tag])) {
                $messages[] = 'Missing required item tag: ' . $this->label($tag) . '.';
            }
        }

        $requiredAny = $this->decodeJson($template['required_any_item_tags']);
        if ($requiredAny !== []) {
            $hasAny = false;
            foreach ($requiredAny as $tag) {
                if (isset($tags[$tag])) {
                    $hasAny = true;
                    break;
                }
            }

            if (!$hasAny) {
                $messages[] = 'Missing one of: ' . implode(', ', array_map([$this, 'label'], $requiredAny)) . '.';
            }
        }

        if ((int) $template['required_crew_count'] > 0 && $this->availableCrewCount((int) $user['id']) < (int) $template['required_crew_count']) {
            $messages[] = 'Requires ' . (int) $template['required_crew_count'] . ' available crew member(s).';
        }

        return $messages;
    }

    private function missingItemsForTemplate(int $userId, array $requiredAll, array $requiredAny): array
    {
        $validation = $this->items->validate($userId, $requiredAll, $requiredAny);

        return $validation['missing'];
    }

    private function validateCooldowns(int $userId, array $template): void
    {
        $cooldown = $this->cooldownState($userId, $template);

        if ($cooldown['active']) {
            throw new RuntimeException('Quick crime is on cooldown for ' . $cooldown['remaining_seconds'] . ' seconds.');
        }
    }

    private function startCooldowns(int $userId, array $template, ?string $districtCode, ?string $targetKey): void
    {
        $pdo = Database::pdo();
        $availableAtExpression = 'DATE_ADD(NOW(), INTERVAL ' . max(1, (int) $template['cooldown_seconds']) . ' SECOND)';

        $pdo->prepare(
            "INSERT INTO quick_crime_cooldowns (
                user_id, template_id, cooldown_type, district_code, target_key, category, available_at, created_at, updated_at
            ) VALUES (?, ?, 'action', '', '', '', {$availableAtExpression}, NOW(), NOW())
            ON DUPLICATE KEY UPDATE available_at = {$availableAtExpression}, updated_at = NOW()"
        )->execute([$userId, $template['id']]);

        $districtCooldown = (int) $template['district_cooldown_seconds'];
        if ($districtCooldown > 0 && $districtCode !== null && $districtCode !== '') {
            $availableDistrict = 'DATE_ADD(NOW(), INTERVAL ' . $districtCooldown . ' SECOND)';
            $pdo->prepare(
                "INSERT INTO quick_crime_cooldowns (
                    user_id, template_id, cooldown_type, district_code, target_key, category, available_at, created_at, updated_at
                ) VALUES (?, ?, 'district', ?, '', ?, {$availableDistrict}, NOW(), NOW())
                ON DUPLICATE KEY UPDATE available_at = {$availableDistrict}, updated_at = NOW()"
            )->execute([$userId, $template['id'], $districtCode, $template['category']]);
        }

        $pdo->prepare(
            <<<'SQL'
                INSERT INTO player_action_cooldowns (
                    user_id,
                    action_type,
                    action_code,
                    available_at,
                    created_at,
                    updated_at
                ) VALUES (?, 'quick_crime', ?, DATE_ADD(NOW(), INTERVAL 10 SECOND), NOW(), NOW())
                ON DUPLICATE KEY UPDATE available_at = DATE_ADD(NOW(), INTERVAL 10 SECOND), updated_at = NOW()
            SQL
        )->execute([$userId, $template['code']]);
    }

    private function cooldownState(int $userId, array $template): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    MAX(available_at) AS available_at,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), MAX(available_at))) AS remaining_seconds
                FROM quick_crime_cooldowns
                WHERE user_id = ?
                  AND template_id = ?
                  AND available_at > NOW()
            SQL
        );
        $statement->execute([$userId, $template['id']]);
        $row = $statement->fetch();

        $remaining = (int) ($row['remaining_seconds'] ?? 0);

        return [
            'active' => $remaining > 0,
            'remaining_seconds' => $remaining,
            'available_at' => $row['available_at'] ?? null,
        ];
    }

    private function resolveLockedRun(
        array $freshUser,
        array $template,
        int $runId,
        ?string $decisionCode,
        array $crew,
        array $effects,
        ?string $districtCode,
        ?string $targetKey
    ): array {
        $pdo = Database::pdo();
        $runStatement = $pdo->prepare('SELECT * FROM quick_crime_runs WHERE id = ? AND user_id = ? FOR UPDATE');
        $runStatement->execute([$runId, $freshUser['id']]);
        $run = $runStatement->fetch();

        if (!$run) {
            throw new RuntimeException('Quick crime run not found.');
        }

        if ((int) $run['resolved'] === 1) {
            return $this->hydrateRun($run);
        }

        $successRoll = $this->random->integer(1, 100);
        $disasterRoll = $this->random->integer(1, 100);
        $successChance = (int) $run['success_chance'];
        $disasterChance = max(0, (int) $run['disaster_chance'] + (int) ($effects['disaster_modifier'] ?? 0));

        $outcome = 'failed_escaped';
        if ($disasterRoll <= $disasterChance) {
            $outcome = 'disaster';
        } elseif ($successRoll <= max(5, $successChance - 18)) {
            $outcome = $successRoll <= 5 ? 'clean_success' : 'success_with_heat';
        } elseif ($successRoll <= $successChance) {
            $outcome = 'partial_success';
        }

        if ($decisionCode === 'abandon' || $decisionCode === 'leave') {
            $outcome = 'abandoned';
        }

        $rewardCash = $this->rewardForOutcome($template, $outcome, (int) ($effects['loot_bonus'] ?? 0), $effects);
        $heatGained = $this->heatForOutcome($template, $outcome, (int) ($effects['heat_modifier'] ?? 0));
        $experienceGained = $this->xpForOutcome($template, $outcome, (int) ($effects['xp_bonus'] ?? 0));
        $loot = $this->grantLoot((int) $freshUser['id'], $template, $outcome);
        $playerXp = $this->experience->grantPlayer(
            (int) $freshUser['id'],
            $experienceGained,
            'quick_crime',
            $runId,
            'Quick crime outcome: ' . $outcome
        );
        $crewXp = [];
        $skillGains = [];

        foreach ($crew as $member) {
            if (($member['actor_type'] ?? 'crew') === 'boss') {
                continue;
            }

            $crewXp[] = $this->experience->grantCrew(
                (int) $freshUser['id'],
                (int) $member['id'],
                max(1, (int) floor($experienceGained / 2)),
                'quick_crime',
                $runId,
                'Participated in quick crime: ' . $template['title']
            );

            $stat = $this->primaryStat($template);
            $gain = $this->skills->maybeImproveCrew(
                (int) $freshUser['id'],
                (int) $member['id'],
                $stat,
                (int) $template['tier'] + (int) $template['min_level'],
                $this->random->integer(1, 100),
                'quick_crime',
                $runId,
                'Learned from quick crime role pressure.'
            );

            if ($gain !== null) {
                $skillGains[] = $gain;
            }
        }

        $playerSkill = $this->skills->maybeImprovePlayer(
            (int) $freshUser['id'],
            $this->primaryStat($template),
            (int) $template['tier'] + (int) $template['min_level'],
            $this->random->integer(1, 100),
            'quick_crime',
            $runId,
            'Learned from a quick street action.'
        );

        if ($playerSkill !== null) {
            $skillGains[] = $playerSkill;
        }

        $bossInvolved = in_array('boss', array_map(static fn (array $member): string => (string) ($member['actor_type'] ?? 'crew'), $crew), true);
        $bossConsequence = null;
        if ($bossInvolved && $outcome === 'disaster') {
            $bossConsequence = (new BossCharacterService())->injureBoss(
                (int) $freshUser['id'],
                (int) $template['tier'] >= 3 ? 'serious' : 'moderate',
                'quick_crime',
                $runId,
                'Boss was injured during a disastrous quick crime outcome.'
            );
        }

        $result = [
            'outcome' => $outcome,
            'title' => $this->outcomeTitle($outcome),
            'description' => $this->outcomeDescription($template, $outcome),
            'decision_code' => $decisionCode,
            'cash_gained' => $rewardCash,
            'loot' => $loot,
            'xp' => $playerXp,
            'crew_xp' => $crewXp,
            'skill_gains' => $skillGains,
            'boss_consequence' => $bossConsequence,
            'cooldown_started' => true,
            'location' => $effects['location_context'] ?? null,
            'local_modifiers' => [
                'reward_multiplier' => $effects['reward_multiplier'] ?? 1.0,
                'heat_multiplier' => $effects['heat_multiplier'] ?? 1.0,
                'police_risk_multiplier' => $effects['police_risk_multiplier'] ?? 1.0,
                'danger_multiplier' => $effects['danger_multiplier'] ?? 1.0,
            ],
        ];

        $pdo->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    cash = cash + ?,
                    heat = GREATEST(0, heat + ?),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$rewardCash, $heatGained, $freshUser['id']]);

        (new HeatPressureService())->recordCrimeHeat(
            (int) $freshUser['id'],
            'quick_crime',
            $runId,
            $heatGained,
            'Quick crime heat: ' . $template['title'],
            array_values(array_filter(array_map(static fn (array $member): int => (($member['actor_type'] ?? 'crew') === 'crew') ? (int) $member['id'] : 0, $crew))),
            null,
            (string) $template['category'],
            in_array('boss', array_map(static fn (array $member): string => (string) ($member['actor_type'] ?? 'crew'), $crew), true)
        );

        $pdo->prepare(
            <<<'SQL'
                UPDATE quick_crime_runs
                SET
                    status = 'resolved',
                    resolved = 1,
                    resolved_at = NOW(),
                    outcome = ?,
                    reward_cash = ?,
                    heat_gained = ?,
                    experience_gained = ?,
                    result = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND user_id = ?
                  AND resolved = 0
            SQL
        )->execute([
            $outcome,
            $rewardCash,
            $heatGained,
            $experienceGained,
            json_encode($result, JSON_THROW_ON_ERROR),
            $runId,
            $freshUser['id'],
        ]);

        $this->startCooldowns((int) $freshUser['id'], $template, $districtCode, $targetKey);
        $this->recordRecentAction((int) $freshUser['id'], $template, $outcome, $heatGained, $rewardCash, $experienceGained, $districtCode, $targetKey);
        $this->recordEconomyLog((int) $freshUser['id'], $runId, $rewardCash, $template['title']);
        AuditService::log((int) $freshUser['id'], 'quick_crime.resolve', [
            'quick_crime_run_id' => $runId,
            'template_code' => $template['code'],
            'outcome' => $outcome,
            'reward_cash' => $rewardCash,
            'heat_gained' => $heatGained,
            'experience_gained' => $experienceGained,
        ]);

        $freshRun = $this->findRun((int) $freshUser['id'], $runId);

        return $this->hydrateRun($freshRun);
    }

    private function rewardForOutcome(array $template, string $outcome, int $lootBonus, array $effects = []): int
    {
        if (in_array($outcome, ['disaster', 'failed_escaped', 'abandoned'], true)) {
            return $outcome === 'failed_escaped' ? (int) floor((int) $template['reward_min'] * 0.15) : 0;
        }

        $reward = $this->random->integer((int) $template['reward_min'], (int) $template['reward_max']);
        $multiplier = match ($outcome) {
            'clean_success' => 1.12,
            'success_with_heat' => 1.0,
            'partial_success' => 0.45,
            default => 0.0,
        };

        return max(0, (int) round(($reward * $multiplier + $lootBonus) * (float) ($effects['reward_multiplier'] ?? 1.0)));
    }

    private function heatForOutcome(array $template, string $outcome, int $heatModifier): int
    {
        $heat = $this->random->integer((int) $template['heat_min'], (int) $template['heat_max']);
        $heat += match ($outcome) {
            'clean_success' => -1,
            'partial_success' => 2,
            'failed_escaped' => 3,
            'disaster' => 8,
            'abandoned' => 0,
            default => 0,
        };

        return max(0, (int) round(($heat + $heatModifier) * (float) ($effects['heat_multiplier'] ?? 1.0)));
    }

    private function xpForOutcome(array $template, string $outcome, int $xpBonus): int
    {
        $xp = $this->random->integer((int) $template['xp_min'], (int) $template['xp_max']);
        $multiplier = match ($outcome) {
            'clean_success' => 1.25,
            'success_with_heat' => 1.0,
            'partial_success' => 0.7,
            'failed_escaped' => 0.45,
            'disaster' => 0.3,
            'abandoned' => 0.1,
            default => 1.0,
        };

        return max(1, (int) round($xp * $multiplier + $xpBonus));
    }

    private function grantLoot(int $userId, array $template, string $outcome): array
    {
        if (in_array($outcome, ['disaster', 'abandoned'], true)) {
            return [];
        }

        $loot = [];
        $table = $this->decodeJson($template['loot_table']);
        foreach ($table as $entry) {
            $chance = (int) ($entry['chance'] ?? 0);
            if ($this->random->integer(1, 100) > $chance) {
                continue;
            }

            $code = (string) ($entry['item_code'] ?? '');
            if ($code === '') {
                continue;
            }

            $quantity = $this->random->integer((int) ($entry['min_quantity'] ?? 1), (int) ($entry['max_quantity'] ?? 1));
            $this->addInventoryItem($userId, $code, $quantity);
            $loot[] = [
                'item_code' => $code,
                'quantity' => $quantity,
            ];
        }

        return $loot;
    }

    private function addInventoryItem(int $userId, string $code, int $quantity): void
    {
        $pdo = Database::pdo();
        $statement = $pdo->prepare('SELECT id FROM item_definitions WHERE code = ? LIMIT 1');
        $statement->execute([$code]);
        $itemId = $statement->fetchColumn();

        if (!$itemId) {
            return;
        }

        $pdo->prepare(
            <<<'SQL'
                INSERT INTO user_items (
                    user_id,
                    item_definition_id,
                    quantity,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = NOW()
            SQL
        )->execute([$userId, $itemId, $quantity]);
    }

    private function createEvent(int $runId, array $template): array
    {
        $pool = $this->eventPool($template);
        $eventCode = $pool[$this->random->integer(0, count($pool) - 1)];
        $event = $this->eventDefinition($eventCode);

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO quick_crime_events (
                    run_id,
                    event_code,
                    title,
                    description,
                    status,
                    choices,
                    created_at
                ) VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            SQL
        )->execute([
            $runId,
            $event['code'],
            $event['title'],
            $event['description'],
            json_encode($event['choices'], JSON_THROW_ON_ERROR),
        ]);

        return [
            'id' => (int) Database::pdo()->lastInsertId(),
            'event_code' => $event['code'],
            'title' => $event['title'],
            'description' => $event['description'],
            'status' => 'pending',
            'choices' => $event['choices'],
        ];
    }

    private function eventDefinition(string $code): array
    {
        $events = [
            'victim_notices' => [
                'title' => 'Target Notices Something',
                'description' => 'The target realizes something feels wrong and turns back toward the crowd.',
            ],
            'police_patrol' => [
                'title' => 'Patrol Nearby',
                'description' => 'A police patrol rolls close enough to make the next move risky.',
            ],
            'silent_alarm' => [
                'title' => 'Silent Alarm',
                'description' => 'The target area suddenly feels too alert, as if someone already pressed an alarm.',
            ],
            'customer_witness' => [
                'title' => 'Customer Witness',
                'description' => 'A civilian witness gets a clear look and may remember details.',
            ],
            'engine_fails' => [
                'title' => 'Engine Problem',
                'description' => 'The vehicle refuses to cooperate at the worst possible moment.',
            ],
            'owner_appears' => [
                'title' => 'Owner Appears',
                'description' => 'The owner walks into the scene before the action is finished.',
            ],
            'neighbor_sees' => [
                'title' => 'Neighbor Watching',
                'description' => 'A nearby window lights up and someone starts watching the street.',
            ],
            'rival_thief' => [
                'title' => 'Rival Thief',
                'description' => 'Another thief was already circling the same target and may interfere.',
            ],
        ];

        $definition = $events[$code] ?? [
            'title' => 'Street Complication',
            'description' => 'Something goes wrong and the street action needs a quick choice.',
        ];

        return [
            'code' => $code,
            'title' => $definition['title'],
            'description' => $definition['description'],
            'choices' => [
                [
                    'code' => 'leave',
                    'label' => 'Leave immediately',
                    'description' => 'Abort most of the reward but lower the chance of serious trouble.',
                    'effects' => ['heat_modifier' => -1, 'loot_bonus' => -30, 'disaster_modifier' => -4],
                ],
                [
                    'code' => 'hide_wait',
                    'label' => 'Hide and wait',
                    'description' => 'Spend time and hope the situation cools down.',
                    'effects' => ['success_bonus' => 3, 'event_modifier' => -3],
                ],
                [
                    'code' => 'continue_quickly',
                    'label' => 'Continue quickly',
                    'description' => 'Keep going with a better reward chance but more heat.',
                    'effects' => ['success_bonus' => 4, 'heat_modifier' => 2, 'disaster_modifier' => 2],
                ],
            ],
        ];
    }

    private function successChance(array $template, array $user, array $crew, array $effects): int
    {
        $chance = (int) $template['base_success_rate'];
        $chance += (int) ($effects['success_bonus'] ?? 0);
        $chance += (int) ($effects['stealth_entry'] ?? 0);
        $chance += (int) ($effects['vehicle_job_bonus'] ?? 0);
        $chance += (int) ($effects['planning_bonus'] ?? 0);
        $chance -= max(0, (int) floor(((int) $user['heat'] - 20) / 8));

        $stats = $this->decodeJson($template['relevant_stats']);
        if ($crew !== [] && $stats !== []) {
            $total = 0;
            $count = 0;
            foreach ($crew as $member) {
                foreach ($stats as $stat) {
                    if (isset($member[$stat])) {
                        $total += (int) $member[$stat];
                        $count++;
                    }
                }
            }

            if ($count > 0) {
                $chance += (int) floor(($total / $count - 45) / 5);
            }
        }

        return max(5, min(95, $chance));
    }

    private function eventChance(array $template, array $user, array $effects): int
    {
        $chance = (int) $template['base_event_chance'];
        $chance += max(0, (int) floor((int) $user['heat'] / 8));
        $chance += (int) ($effects['event_modifier'] ?? 0);

        return max(0, min(90, (int) round($chance * (float) ($effects['police_risk_multiplier'] ?? 1.0))));
    }

    private function disasterChance(array $template, array $user, array $effects): int
    {
        $chance = (int) $template['base_disaster_chance'];
        $chance += max(0, (int) floor(((int) $user['heat'] - 40) / 10));
        $chance += (int) ($effects['disaster_modifier'] ?? 0);
        $chance += (int) ($effects['injury_modifier'] ?? 0);

        return max(0, min(50, (int) round($chance * (float) ($effects['danger_multiplier'] ?? 1.0))));
    }

    private function validateCrew(int $userId, array $crewIds, int $requiredCrewCount): array
    {
        $crewIds = array_values(array_unique(array_map('intval', $crewIds)));
        $includesBoss = in_array(0, $crewIds, true);
        $realCrewIds = array_values(array_filter($crewIds, static fn (int $id): bool => $id > 0));
        $actors = [];

        if ($includesBoss) {
            $actors[] = $this->bossActor($userId);
        }

        if (count($crewIds) < $requiredCrewCount) {
            throw new RuntimeException('More crew are required for this quick crime.');
        }

        if ($realCrewIds !== []) {
            $placeholders = implode(',', array_fill(0, count($realCrewIds), '?'));
            $statement = Database::pdo()->prepare(
                "SELECT *, 'crew' AS actor_type, id AS actor_id FROM player_gang_members WHERE user_id = ? AND id IN ({$placeholders}) AND status = 'active' FOR UPDATE"
            );
            $statement->execute(array_merge([$userId], $realCrewIds));
            $members = $statement->fetchAll();

            if (count($members) !== count($realCrewIds)) {
                throw new RuntimeException('One selected crew member is unavailable or does not belong to you.');
            }

            $actors = array_merge($actors, $members);
        }

        return $actors;
    }

    private function bossActor(int $userId): array
    {
        $boss = (new BossCharacterService())->profile(['id' => $userId]);

        return [
            'id' => 0,
            'actor_type' => 'boss',
            'actor_id' => $userId,
            'strength' => $boss['skills']['strength'],
            'shooting' => $boss['skills']['shooting'],
            'driving' => $boss['skills']['driving'],
            'intelligence' => $boss['skills']['intelligence'],
            'stealth' => $boss['skills']['stealth'],
            'intimidation' => $boss['skills']['intimidation'],
            'discipline' => $boss['skills']['discipline'],
            'street_knowledge' => $boss['skills']['street_knowledge'],
            'endurance' => $boss['skills']['endurance'],
            'loyalty' => 100,
            'morale' => 100,
        ];
    }

    private function validateEquipmentSelection(array $equipment, array $inventory): void
    {
        $owned = [];
        foreach (array_merge($inventory['items'], $inventory['weapons']) as $asset) {
            $owned[$asset['asset_type'] . ':' . $asset['asset_id']] = true;
        }

        foreach ($equipment as $entry) {
            $key = (string) ($entry['asset_type'] ?? 'item') . ':' . (int) ($entry['asset_id'] ?? 0);
            if (!isset($owned[$key])) {
                throw new RuntimeException('Selected equipment is not owned.');
            }
        }
    }

    private function crewForRun(int $userId, int $runId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT run_crew.*, member.*
                FROM quick_crime_run_crew run_crew
                LEFT JOIN player_gang_members member
                    ON member.id = run_crew.gang_member_id
                    AND run_crew.actor_type = 'crew'
                WHERE run_crew.user_id = ?
                  AND run_crew.run_id = ?
                ORDER BY run_crew.id
            SQL
        );
        $statement->execute([$userId, $runId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            if (($row['actor_type'] ?? 'crew') === 'boss') {
                $row = [
                    ...$row,
                    ...$this->bossActor($userId),
                ];
            }
        }

        return $rows;
    }

    private function storeRunCrew(int $userId, int $runId, array $crew): void
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO quick_crime_run_crew (
                    run_id,
                    user_id,
                    actor_type,
                    actor_id,
                    gang_member_id,
                    role_code,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            SQL
        );

        foreach ($crew as $index => $member) {
            $actorType = (string) ($member['actor_type'] ?? 'crew');
            $actorId = $actorType === 'boss' ? $userId : (int) $member['id'];
            $gangMemberId = $actorType === 'crew' ? (int) $member['id'] : null;

            $statement->execute([
                $runId,
                $userId,
                $actorType,
                $actorId,
                $gangMemberId,
                $actorType === 'boss' ? 'lead' : ($index === 0 ? 'lead' : 'helper'),
            ]);
        }
    }

    private function storeRunEquipment(int $userId, int $runId, array $equipment, array $inventory): void
    {
        if ($equipment === []) {
            return;
        }

        $owned = [];
        foreach (array_merge($inventory['items'], $inventory['weapons']) as $asset) {
            $owned[$asset['asset_type'] . ':' . $asset['asset_id']] = $asset;
        }

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO quick_crime_run_equipment (
                    run_id,
                    user_id,
                    asset_type,
                    asset_id,
                    quantity,
                    durability_before,
                    durability_after,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 100, 98, NOW())
                ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
            SQL
        );

        foreach ($equipment as $entry) {
            $assetType = (string) ($entry['asset_type'] ?? 'item');
            $assetId = (int) ($entry['asset_id'] ?? 0);
            $key = $assetType . ':' . $assetId;

            if (!isset($owned[$key])) {
                continue;
            }

            $statement->execute([
                $runId,
                $userId,
                $assetType,
                $assetId,
                max(1, (int) ($entry['quantity'] ?? 1)),
            ]);
        }
    }

    private function recordRecentAction(int $userId, array $template, string $outcome, int $heat, int $reward, int $xp, ?string $districtCode, ?string $targetKey): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO player_recent_actions (
                    user_id,
                    action_type,
                    action_code,
                    category,
                    district_code,
                    target_key,
                    outcome,
                    heat_gained,
                    reward_cash,
                    experience_gained,
                    created_at
                ) VALUES (?, 'quick_crime', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            $userId,
            $template['code'],
            $template['category'],
            $districtCode,
            $targetKey,
            $outcome,
            $heat,
            $reward,
            $xp,
        ]);
    }

    private function recordEconomyLog(int $userId, int $runId, int $amount, string $title): void
    {
        if ($amount <= 0) {
            return;
        }

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO economy_transactions (
                    category,
                    amount,
                    currency,
                    source_type,
                    source_id,
                    destination_type,
                    destination_id,
                    user_id,
                    description,
                    created_at
                ) VALUES ('quick_crime_reward', ?, 'cash', 'quick_crime_run', ?, 'user', ?, ?, ?, NOW())
            SQL
        )->execute([
            $amount,
            $runId,
            $userId,
            $userId,
            'Quick crime reward: ' . $title,
        ]);
    }

    private function hydrateRun(array|false|null $run, ?array $eventOverride = null): array
    {
        if (!$run) {
            return [];
        }

        $event = $eventOverride;
        if ($event === null && (int) $run['resolved'] === 0) {
            $event = $this->pendingEvent((int) $run['id']);
            if ($event) {
                $event['choices'] = $this->decodeJson($event['choices']);
            }
        }

        return [
            'id' => (int) $run['id'],
            'template_id' => (int) $run['template_id'],
            'status' => $run['status'],
            'outcome' => $run['outcome'],
            'success_chance' => (int) $run['success_chance'],
            'event_chance' => (int) $run['event_chance'],
            'disaster_chance' => (int) $run['disaster_chance'],
            'reward_cash' => (int) $run['reward_cash'],
            'reward_dirty_cash' => (int) $run['reward_dirty_cash'],
            'heat_gained' => (int) $run['heat_gained'],
            'experience_gained' => (int) $run['experience_gained'],
            'result' => $this->decodeJson($run['result']),
            'event' => $event,
            'started_at' => $run['started_at'] ?? null,
            'resolved_at' => $run['resolved_at'] ?? null,
        ];
    }

    private function activeRuns(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM quick_crime_runs
                WHERE user_id = ?
                  AND status IN ('active', 'awaiting_decision')
                  AND resolved = 0
                ORDER BY id DESC
            SQL
        );
        $statement->execute([$userId]);

        return array_map(fn (array $run): array => $this->hydrateRun($run), $statement->fetchAll());
    }

    private function recentExperience(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM experience_logs WHERE user_id = ? ORDER BY id DESC LIMIT 12'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    private function recentSkillGains(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM skill_progression_logs WHERE user_id = ? ORDER BY id DESC LIMIT 12'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    private function findTemplate(int $templateId): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM quick_crime_templates WHERE id = ? AND active = 1 LIMIT 1');
        $statement->execute([$templateId]);
        $template = $statement->fetch();

        return $template ?: null;
    }

    private function lockTemplate(int $templateId): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM quick_crime_templates WHERE id = ? AND active = 1 FOR UPDATE');
        $statement->execute([$templateId]);
        $template = $statement->fetch();

        return $template ?: null;
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

    private function lockRun(int $userId, int $runId): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM quick_crime_runs WHERE id = ? AND user_id = ? FOR UPDATE');
        $statement->execute([$runId, $userId]);
        $run = $statement->fetch();

        return $run ?: null;
    }

    private function findRun(int $userId, int $runId): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM quick_crime_runs WHERE id = ? AND user_id = ? LIMIT 1');
        $statement->execute([$runId, $userId]);
        $run = $statement->fetch();

        return $run ?: null;
    }

    private function runByIdempotency(int $userId, string $idempotencyKey): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM quick_crime_runs WHERE user_id = ? AND idempotency_key = ? LIMIT 1');
        $statement->execute([$userId, $idempotencyKey]);
        $run = $statement->fetch();

        return $run ?: null;
    }

    private function pendingEvent(int $runId): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM quick_crime_events WHERE run_id = ? AND status = ? ORDER BY id DESC LIMIT 1'
        );
        $statement->execute([$runId, 'pending']);
        $event = $statement->fetch();

        return $event ?: null;
    }

    private function loadPreparations(int $userId, int $templateId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM quick_crime_preparations
                WHERE user_id = ?
                  AND template_id = ?
                  AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY id
            SQL
        );
        $statement->execute([$userId, $templateId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['effects'] = $this->decodeJson($row['effects']);
        }

        return $rows;
    }

    private function aggregateEffects(array $preparations): array
    {
        $effects = [];

        foreach ($preparations as $preparation) {
            foreach (($preparation['effects'] ?? []) as $effect => $value) {
                if (is_numeric($value)) {
                    $effects[$effect] = ($effects[$effect] ?? 0) + (int) $value;
                }
            }
        }

        return $effects;
    }

    private function preparationOption(array $template, string $code): ?array
    {
        foreach ($this->decodeJson($template['preparation_options']) as $option) {
            if (($option['code'] ?? '') === $code) {
                return $option;
            }
        }

        return null;
    }

    private function availableCrewCount(int $userId): int
    {
        $statement = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM player_gang_members WHERE user_id = ? AND status = 'active'"
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    private function eventPool(array $template): array
    {
        $pool = $this->decodeJson($template['event_pool']);

        return array_values(array_filter($pool, 'is_string'));
    }

    private function primaryStat(array $template): string
    {
        $stats = $this->decodeJson($template['relevant_stats']);

        return is_string($stats[0] ?? null) ? $stats[0] : 'street_knowledge';
    }

    private function outcomeTitle(string $outcome): string
    {
        return match ($outcome) {
            'clean_success' => 'Clean success',
            'success_with_heat' => 'Success with heat',
            'partial_success' => 'Partial success',
            'failed_escaped' => 'Failed but escaped',
            'disaster' => 'Disaster avoided barely',
            'abandoned' => 'Action abandoned',
            default => 'Quick crime resolved',
        };
    }

    private function outcomeDescription(array $template, string $outcome): string
    {
        return match ($outcome) {
            'clean_success' => 'The action was finished cleanly with little attention.',
            'success_with_heat' => 'The action worked, but it created attention in the street.',
            'partial_success' => 'Only part of the plan worked, but the player learned from it.',
            'failed_escaped' => 'The player failed to get much value but escaped before things got worse.',
            'disaster' => 'The situation went badly and consequences increased, but early-game losses remain survivable.',
            'abandoned' => 'The player backed out before the situation escalated.',
            default => 'The quick street action resolved.',
        };
    }

    private function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);

        return $key !== '' ? substr($key, 0, 80) : bin2hex(random_bytes(16));
    }

    private function label(string $tag): string
    {
        return ucwords(str_replace('_', ' ', $tag));
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
