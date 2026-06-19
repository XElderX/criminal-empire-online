<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class HeatPressureService
{
    public function overview(array $user): array
    {
        $userId = (int) $user['id'];
        $this->ensureBossFields($userId);
        $boss = (new BossCharacterService())->profile($user);
        $crew = $this->crewHeat($userId);
        $districts = $this->districtHeat();
        $investigations = (new InvestigationService())->listForUser($userId);
        $logs = $this->logs($userId, 30);
        $highestCrewHeat = $crew === [] ? 0 : max(array_column($crew, 'personal_heat'));
        $displayHeat = max($boss['personal_heat'], $boss['gang_heat'], $highestCrewHeat, (int) ($user['heat'] ?? 0));

        return [
            'boss' => $boss,
            'gang' => [
                'heat' => $boss['gang_heat'],
                'level' => $this->levelFor($boss['gang_heat']),
                'forecast' => $this->forecast($displayHeat, count($investigations)),
                'idle_days_count' => (int) ($user['idle_days_count'] ?? 0),
                'last_heat_generating_action_at' => $user['last_heat_generating_action_at'] ?? null,
            ],
            'display_heat' => $displayHeat,
            'display_heat_level' => $this->levelFor($displayHeat),
            'crew' => $crew,
            'highest_crew_heat' => $highestCrewHeat,
            'districts' => $districts,
            'investigations' => $investigations,
            'recent_logs' => $logs,
            'reduction_options' => $this->reductionOptions($user),
            'warnings' => $this->warnings($boss, $highestCrewHeat, $investigations),
        ];
    }

    public function logs(int $userId, int $limit = 60): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM heat_logs
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT ?
            SQL
        );
        $statement->bindValue(1, $userId);
        $statement->bindValue(2, $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function reductionOptions(array $user): array
    {
        $statement = Database::pdo()->query(
            <<<'SQL'
                SELECT *
                FROM heat_reduction_actions
                WHERE active = 1
                ORDER BY FIELD(code, 'lie_low_short','lie_low_full_day','bribe_contact','pay_lawyer','destroy_evidence','send_crew_away'), id
            SQL
        );
        $options = [];

        foreach ($statement->fetchAll() as $action) {
            $locked = [];
            if ((int) ($user['cash'] ?? 0) < (int) $action['cash_cost']) {
                $locked[] = 'Not enough cash.';
            }
            if ((int) ($user['energy'] ?? 0) < (int) $action['energy_cost']) {
                $locked[] = 'Not enough energy.';
            }
            if ($this->actionOnCooldown((int) $user['id'], (string) $action['code'])) {
                $locked[] = 'Action is on cooldown.';
            }
            if ($action['code'] === 'send_crew_away' && $this->highHeatCrew((int) $user['id']) === null) {
                $locked[] = 'No high-heat active crew member is available.';
            }

            $options[] = [
                'code' => $action['code'],
                'name' => $action['name'],
                'description' => $action['description'],
                'target_type' => $action['target_type'],
                'heat_reduction_min' => (int) $action['heat_reduction_min'],
                'heat_reduction_max' => (int) $action['heat_reduction_max'],
                'investigation_reduction_min' => (int) $action['investigation_reduction_min'],
                'investigation_reduction_max' => (int) $action['investigation_reduction_max'],
                'cash_cost' => (int) $action['cash_cost'],
                'energy_cost' => (int) $action['energy_cost'],
                'cooldown_seconds' => (int) $action['cooldown_seconds'],
                'risk_percent' => (int) $action['risk_percent'],
                'locked_reasons' => $locked,
                'can_use' => $locked === [],
            ];
        }

        return $options;
    }

    public function applyHeat(
        int $userId,
        string $targetType,
        ?int $targetId,
        int $amount,
        string $category,
        string $sourceType,
        ?int $sourceId,
        string $description,
        bool $spillover = true,
        bool $evidenceLinked = false
    ): array {
        $amount = max(0, $amount);
        if ($amount === 0) {
            return ['amount' => 0];
        }

        $pdo = Database::pdo();
        match ($targetType) {
            'boss' => $pdo->prepare('UPDATE users SET boss_personal_heat = boss_personal_heat + ?, heat = GREATEST(heat, boss_personal_heat + ?), last_heat_generating_action_at = NOW(), updated_at = NOW() WHERE id = ?')
                ->execute([$amount, $amount, $userId]),
            'gang' => $pdo->prepare('UPDATE users SET gang_heat = gang_heat + ?, heat = GREATEST(heat, gang_heat + ?), last_heat_generating_action_at = NOW(), updated_at = NOW() WHERE id = ?')
                ->execute([$amount, $amount, $userId]),
            'crew' => $pdo->prepare('UPDATE player_gang_members SET personal_heat = personal_heat + ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
                ->execute([$amount, $targetId, $userId]),
            'npc' => $pdo->prepare('UPDATE npcs SET personal_heat = personal_heat + ?, updated_at = NOW() WHERE id = ?')
                ->execute([$amount, $targetId]),
            'district' => $pdo->prepare('UPDATE territories SET district_heat = district_heat + ? WHERE id = ?')
                ->execute([$amount, $targetId]),
            default => null,
        };

        if ($targetType !== 'gang' && $spillover) {
            $gangAmount = max(1, (int) floor($amount * 0.35));
            $pdo->prepare('UPDATE users SET gang_heat = gang_heat + ?, heat = GREATEST(heat, gang_heat + ?), last_heat_generating_action_at = NOW(), updated_at = NOW() WHERE id = ?')
                ->execute([$gangAmount, $gangAmount, $userId]);
        }

        $this->log($userId, $targetType, $targetId, $amount, $category, $sourceType, $sourceId, $description, $spillover, $evidenceLinked);
        $currentHeat = $this->targetHeat($userId, $targetType, $targetId);
        $investigation = (new InvestigationService())->openOrAdvance($userId, $targetType, $targetId, $currentHeat, $sourceType, $sourceId, $description);

        return [
            'amount' => $amount,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_heat' => $currentHeat,
            'investigation' => $investigation,
        ];
    }

    public function recordCrimeHeat(
        int $userId,
        string $sourceType,
        ?int $sourceId,
        int $heat,
        string $description,
        array $crewMemberIds = [],
        ?int $districtId = null,
        string $category = 'police_attention'
    ): void {
        $crewMemberIds = array_values(array_unique(array_filter(array_map('intval', $crewMemberIds))));

        if ($crewMemberIds === []) {
            $this->applyHeat($userId, 'boss', null, $heat, $category, $sourceType, $sourceId, $description, true, $heat >= 5);
        } else {
            foreach ($crewMemberIds as $crewMemberId) {
                $this->applyHeat($userId, 'crew', $crewMemberId, max(1, (int) floor($heat * 0.85)), $category, $sourceType, $sourceId, $description, true, $heat >= 5);
            }
            $this->applyHeat($userId, 'gang', null, max(1, (int) floor($heat * 0.25)), 'gang_heat', $sourceType, $sourceId, 'Gang association from crew action.', false, false);
        }

        if ($districtId !== null && $heat > 0) {
            $this->applyHeat($userId, 'district', $districtId, max(1, (int) floor($heat * 0.4)), 'district_heat', $sourceType, $sourceId, 'District activity pressure increased.', false, false);
        }
    }

    public function reduce(array $user, string $code, array $payload = []): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $action = $this->reductionAction($code);
            if (!$action) {
                throw new RuntimeException('Heat reduction action not found.');
            }

            if ($this->actionOnCooldown((int) $user['id'], $code)) {
                throw new RuntimeException('This heat reduction action is on cooldown.');
            }

            $fresh = $this->lockUser((int) $user['id']);
            if ((int) $fresh['cash'] < (int) $action['cash_cost']) {
                throw new RuntimeException('Not enough cash for this heat action.');
            }
            if ((int) $fresh['energy'] < (int) $action['energy_cost']) {
                throw new RuntimeException('Not enough energy for this heat action.');
            }

            $reduction = random_int((int) $action['heat_reduction_min'], (int) $action['heat_reduction_max']);
            $investigationReduction = random_int((int) $action['investigation_reduction_min'], (int) $action['investigation_reduction_max']);
            $message = 'Heat reduction completed.';
            $extra = [];

            $pdo->prepare('UPDATE users SET cash = cash - ?, energy = energy - ?, updated_at = NOW() WHERE id = ?')
                ->execute([(int) $action['cash_cost'], (int) $action['energy_cost'], $fresh['id']]);

            if ($code === 'send_crew_away') {
                $memberId = isset($payload['crew_member_id']) ? (int) $payload['crew_member_id'] : 0;
                $member = $memberId > 0 ? $this->lockCrew((int) $fresh['id'], $memberId) : $this->highHeatCrew((int) $fresh['id']);
                if (!$member) {
                    throw new RuntimeException('No eligible high-heat crew member found.');
                }

                $actual = min($reduction, (int) $member['personal_heat']);
                $pdo->prepare(
                    <<<'SQL'
                        UPDATE player_gang_members
                        SET
                            personal_heat = GREATEST(0, personal_heat - ?),
                            sent_away_until = DATE_ADD(NOW(), INTERVAL 3 DAY),
                            status = 'busy',
                            updated_at = NOW()
                        WHERE id = ?
                          AND user_id = ?
                    SQL
                )->execute([$actual, $member['id'], $fresh['id']]);
                $this->log((int) $fresh['id'], 'crew', (int) $member['id'], -$actual, 'personal_heat_reduction', 'heat_reduction', null, 'Crew member sent away to cool personal heat.', false, false);
                $message = 'High-heat crew member was sent away to cool down.';
                $extra['crew_member_id'] = (int) $member['id'];
            } else {
                $actual = $this->reduceUserHeat((int) $fresh['id'], $reduction, $code);
                $this->log((int) $fresh['id'], 'gang', null, -$actual, 'heat_reduction', 'heat_reduction', null, $action['name'], false, false);
                $message = $action['name'] . ' reduced police pressure.';
            }

            if ($investigationReduction > 0) {
                (new InvestigationService())->reducePressure((int) $fresh['id'], $investigationReduction, isset($payload['investigation_id']) ? (int) $payload['investigation_id'] : null);
            }

            if ((int) $action['risk_percent'] > 0 && random_int(1, 100) <= (int) $action['risk_percent']) {
                $this->applyHeat((int) $fresh['id'], 'gang', null, 4, 'failed_heat_reduction', 'heat_reduction', null, 'A risky heat reduction attempt created more suspicion.', true, true);
                $message .= ' It also created some suspicion.';
            }

            $this->startActionCooldown((int) $fresh['id'], $code, (int) $action['cooldown_seconds']);
            $pdo->commit();

            return [
                'message' => $message,
                'heat_reduced' => $reduction,
                'investigation_pressure_reduced' => $investigationReduction,
                'extra' => $extra,
                'overview' => $this->overview($fresh),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function processDaily(int $userId, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $user = $this->lockUser($userId);
            $state = $this->processingState($userId);
            if (($state['last_processed_date'] ?? null) === $date) {
                $pdo->commit();
                return ['message' => 'Heat processing already completed for this day.', 'idempotent' => true];
            }

            $criminalToday = $this->hasHeatGeneratingAction($userId, $date);
            $dailyReduction = 0;
            $weeklyReduction = 0;
            $idleDays = $criminalToday ? 0 : ((int) ($state['idle_days_count'] ?? 0) + 1);

            if (!$criminalToday) {
                $dailyReduction = $this->reduceUserHeat($userId, 5, 'idle_daily_decay');
                $this->reduceCrewHeat($userId, 5);
                $this->log($userId, 'gang', null, -$dailyReduction, 'idle_decay', 'world_processing', null, 'Quiet day idle decay reduced heat by 5 where available.', false, false);
            }

            $weekKey = date('o-W', strtotime($date));
            if (!$criminalToday && $idleDays >= 7 && ($state['last_weekly_bonus_key'] ?? null) !== $weekKey) {
                $weeklyReduction = $this->reduceUserHeat($userId, 15, 'weekly_quiet_bonus');
                $this->reduceCrewHeat($userId, 15);
                $this->log($userId, 'gang', null, -$weeklyReduction, 'weekly_quiet_bonus', 'world_processing', null, 'Seven quiet days added the weekly quiet heat reduction bonus.', false, false);
            }

            $investigation = (new InvestigationService())->advanceOpenInvestigations($userId);
            $this->updateProcessingState($userId, $date, $idleDays, $weeklyReduction > 0 ? $weekKey : ($state['last_weekly_bonus_key'] ?? null));
            $pdo->commit();

            return [
                'message' => 'Daily heat and police pressure processed.',
                'criminal_today' => $criminalToday,
                'idle_days_count' => $idleDays,
                'daily_heat_reduced' => $dailyReduction,
                'weekly_heat_reduced' => $weeklyReduction,
                'investigations' => $investigation,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function dismissHeatRelief(array $user, array $member): array
    {
        $heat = (int) ($member['personal_heat'] ?? 0);
        if ($heat < 20) {
            return [
                'heat_relief' => 0,
                'revenge_risk' => 0,
                'revenge_event_created' => false,
            ];
        }

        $relief = min(18, max(4, (int) floor($heat * 0.35)));
        $revengeRisk = min(95, max(10, (int) floor($heat * 0.85) + max(0, 55 - (int) ($member['loyalty'] ?? 50))));
        $pdo = Database::pdo();

        $pdo->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    boss_personal_heat = GREATEST(0, boss_personal_heat - FLOOR(? / 2)),
                    gang_heat = GREATEST(0, gang_heat - ?),
                    heat = GREATEST(0, heat - ?),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$relief, $relief, $relief, $user['id']]);

        $pdo->prepare(
            <<<'SQL'
                UPDATE player_gang_members
                SET
                    revenge_risk = ?,
                    revenge_status = IF(? >= 45, 'furious', 'watching'),
                    dismissed_heat_relief = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND user_id = ?
            SQL
        )->execute([$revengeRisk, $revengeRisk, $relief, $member['id'], $user['id']]);

        $this->log((int) $user['id'], 'gang', null, -$relief, 'dismissed_crew_heat_relief', 'crew.dismiss', (int) $member['id'], 'Dismissed high-heat crew lowered boss/gang pressure but created revenge risk.', false, false);

        $created = false;
        if ($revengeRisk >= 45) {
            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO crew_revenge_events (
                        user_id,
                        gang_member_id,
                        npc_id,
                        event_type,
                        severity,
                        status,
                        heat_at_dismissal,
                        revenge_risk,
                        title,
                        description,
                        scheduled_at,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, 'revenge_plot', ?, 'pending', ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW(), NOW())
                SQL
            )->execute([
                $user['id'],
                $member['id'],
                $member['npc_id'],
                $revengeRisk >= 70 ? 'high' : 'medium',
                $heat,
                $revengeRisk,
                'Dismissed crew member is furious',
                'A dismissed high-heat crew member may attempt sabotage, informants, or revenge violence later.',
                $revengeRisk >= 70 ? 1 : 3,
            ]);
            $created = true;
        }

        return [
            'heat_relief' => $relief,
            'revenge_risk' => $revengeRisk,
            'revenge_event_created' => $created,
        ];
    }

    public function crewHeat(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    member.id,
                    member.npc_id,
                    member.status,
                    member.personal_heat,
                    member.under_investigation,
                    member.sent_away_until,
                    member.revenge_risk,
                    member.revenge_status,
                    member.level,
                    member.loyalty,
                    member.discipline,
                    npc.first_name,
                    npc.last_name,
                    npc.nickname,
                    npc.portrait_set_key,
                    npc.gender
                FROM player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                WHERE member.user_id = ?
                ORDER BY member.personal_heat DESC, member.id DESC
            SQL
        );
        $statement->execute([$userId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['personal_heat'] = (int) $row['personal_heat'];
            $row['heat_level'] = $this->levelFor((int) $row['personal_heat']);
            $row['recommendation'] = $this->crewRecommendation($row);
            $row['under_investigation'] = (bool) $row['under_investigation'];
        }

        return $rows;
    }

    private function districtHeat(): array
    {
        $statement = Database::pdo()->query(
            <<<'SQL'
                SELECT id, name, police_presence, district_heat
                FROM territories
                ORDER BY district_heat DESC, police_presence DESC
                LIMIT 20
            SQL
        );
        $districts = $statement->fetchAll();
        foreach ($districts as &$district) {
            $district['district_heat'] = (int) $district['district_heat'];
            $district['heat_level'] = $this->levelFor((int) $district['district_heat']);
        }

        return $districts;
    }

    private function warnings(array $boss, int $highestCrewHeat, array $investigations): array
    {
        $warnings = [];
        if ($boss['personal_heat'] >= 75) {
            $warnings[] = 'Boss heat is hot. Arrest, injury, or search events become more likely.';
        }
        if ($boss['gang_heat'] >= 75) {
            $warnings[] = 'Gang heat is hot. Police may connect crew, warehouses, and contacts.';
        }
        if ($highestCrewHeat >= 60) {
            $warnings[] = 'One or more crew members have high personal heat and can spill pressure onto the gang.';
        }
        if (count($investigations) > 0) {
            $warnings[] = 'Active investigations exist. Use legal/bribe/cleanup actions before they escalate.';
        }

        return $warnings;
    }

    private function forecast(int $heat, int $investigations): string
    {
        $score = $heat + ($investigations * 10);
        return match (true) {
            $score >= 100 => 'Critical: immediate police action possible',
            $score >= 75 => 'Severe: raids or arrests possible',
            $score >= 50 => 'High: investigation likely',
            $score >= 25 => 'Moderate: patrols and questions more likely',
            default => 'Low: police unlikely to act',
        };
    }

    private function levelFor(int $heat): array
    {
        return match (true) {
            $heat >= 100 => ['key' => 'critical', 'label' => 'Critical', 'description' => 'Intense police pressure. Major consequences are possible.'],
            $heat >= 75 => ['key' => 'hot', 'label' => 'Hot', 'description' => 'Serious investigation risk. Crew can be followed.'],
            $heat >= 50 => ['key' => 'wanted_locally', 'label' => 'Wanted Locally', 'description' => 'Investigations may open. Stop/search risk rises.'],
            $heat >= 25 => ['key' => 'noticed', 'label' => 'Noticed', 'description' => 'Patrols increase and NPCs become cautious.'],
            $heat >= 10 => ['key' => 'warm', 'label' => 'Warm', 'description' => 'Low police awareness and minor warnings.'],
            default => ['key' => 'clean', 'label' => 'Clean', 'description' => 'No meaningful police attention.'],
        };
    }

    private function reductionAction(string $code): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM heat_reduction_actions WHERE code = ? AND active = 1 LIMIT 1');
        $statement->execute([$code]);
        $action = $statement->fetch();

        return $action ?: null;
    }

    private function reduceUserHeat(int $userId, int $amount, string $reason): int
    {
        $amount = max(0, $amount);
        if ($amount <= 0) {
            return 0;
        }

        $before = Database::pdo()->prepare('SELECT heat + boss_personal_heat + gang_heat FROM users WHERE id = ?');
        $before->execute([$userId]);
        $beforeValue = (int) $before->fetchColumn();

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE users
                SET
                    heat = GREATEST(0, heat - ?),
                    boss_personal_heat = GREATEST(0, boss_personal_heat - ?),
                    gang_heat = GREATEST(0, gang_heat - ?),
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$amount, $amount, $amount, $userId]);

        $after = Database::pdo()->prepare('SELECT heat + boss_personal_heat + gang_heat FROM users WHERE id = ?');
        $after->execute([$userId]);

        return max(0, $beforeValue - (int) $after->fetchColumn());
    }

    private function reduceCrewHeat(int $userId, int $amount): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE player_gang_members
                SET personal_heat = GREATEST(0, personal_heat - ?), updated_at = NOW()
                WHERE user_id = ?
                  AND personal_heat > 0
            SQL
        )->execute([$amount, $userId]);
    }

    private function hasHeatGeneratingAction(int $userId, string $date): bool
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT 1
                FROM heat_logs
                WHERE user_id = ?
                  AND amount > 0
                  AND DATE(created_at) = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$userId, $date]);

        return (bool) $statement->fetchColumn();
    }

    private function processingState(int $userId): array
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO heat_processing_state (user_id, created_at, updated_at)
                VALUES (?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE updated_at = updated_at
            SQL
        )->execute([$userId]);

        $statement = Database::pdo()->prepare('SELECT * FROM heat_processing_state WHERE user_id = ? LIMIT 1');
        $statement->execute([$userId]);

        return $statement->fetch() ?: [];
    }

    private function updateProcessingState(int $userId, string $date, int $idleDays, ?string $weekKey): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE heat_processing_state
                SET last_processed_date = ?, idle_days_count = ?, last_weekly_bonus_key = ?, updated_at = NOW()
                WHERE user_id = ?
            SQL
        )->execute([$date, $idleDays, $weekKey, $userId]);

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE users
                SET idle_days_count = ?, last_idle_decay_processed_date = ?, weekly_quiet_bonus_last_week = ?, updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$idleDays, $date, $weekKey, $userId]);
    }

    private function actionOnCooldown(int $userId, string $code): bool
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT 1
                FROM player_action_cooldowns
                WHERE user_id = ?
                  AND action_type = 'heat_reduction'
                  AND action_code = ?
                  AND available_at > NOW()
                LIMIT 1
            SQL
        );
        $statement->execute([$userId, $code]);

        return (bool) $statement->fetchColumn();
    }

    private function startActionCooldown(int $userId, string $code, int $seconds): void
    {
        $seconds = max(60, $seconds);
        Database::pdo()->prepare(
            "INSERT INTO player_action_cooldowns (user_id, action_type, action_code, available_at, created_at, updated_at)
             VALUES (?, 'heat_reduction', ?, DATE_ADD(NOW(), INTERVAL {$seconds} SECOND), NOW(), NOW())
             ON DUPLICATE KEY UPDATE available_at = DATE_ADD(NOW(), INTERVAL {$seconds} SECOND), updated_at = NOW()"
        )->execute([$userId, $code]);
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

    private function lockCrew(int $userId, int $memberId): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM player_gang_members WHERE id = ? AND user_id = ? AND status <> \'dead\' FOR UPDATE');
        $statement->execute([$memberId, $userId]);
        $member = $statement->fetch();
        if (!$member) {
            throw new RuntimeException('Crew member not found.');
        }
        return $member;
    }

    private function highHeatCrew(int $userId): ?array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM player_gang_members
                WHERE user_id = ?
                  AND status = 'active'
                  AND personal_heat >= 25
                ORDER BY personal_heat DESC, loyalty ASC
                LIMIT 1
            SQL
        );
        $statement->execute([$userId]);
        $member = $statement->fetch();

        return $member ?: null;
    }

    private function targetHeat(int $userId, string $targetType, ?int $targetId): int
    {
        $pdo = Database::pdo();
        if ($targetType === 'boss') {
            $s = $pdo->prepare('SELECT boss_personal_heat FROM users WHERE id = ?');
            $s->execute([$userId]);
            return (int) $s->fetchColumn();
        }
        if ($targetType === 'gang') {
            $s = $pdo->prepare('SELECT gang_heat FROM users WHERE id = ?');
            $s->execute([$userId]);
            return (int) $s->fetchColumn();
        }
        if ($targetType === 'crew') {
            $s = $pdo->prepare('SELECT personal_heat FROM player_gang_members WHERE id = ? AND user_id = ?');
            $s->execute([$targetId, $userId]);
            return (int) $s->fetchColumn();
        }
        if ($targetType === 'npc') {
            $s = $pdo->prepare('SELECT personal_heat FROM npcs WHERE id = ?');
            $s->execute([$targetId]);
            return (int) $s->fetchColumn();
        }
        if ($targetType === 'district') {
            $s = $pdo->prepare('SELECT district_heat FROM territories WHERE id = ?');
            $s->execute([$targetId]);
            return (int) $s->fetchColumn();
        }
        return 0;
    }

    private function crewRecommendation(array $row): string
    {
        $heat = (int) $row['personal_heat'];
        if ($heat >= 75) {
            return 'Send away or pay lawyer immediately; spillover risk is severe.';
        }
        if ($heat >= 50) {
            return 'Bench this crew member or destroy evidence before assigning jobs.';
        }
        if ($heat >= 25) {
            return 'Use quieter jobs and watch for investigations.';
        }
        return 'No special heat action needed.';
    }

    private function ensureBossFields(int $userId): void
    {
        (new BossCharacterService())->ensureProfile($userId);
    }

    private function log(?int $userId, string $targetType, ?int $targetId, int $amount, string $category, string $sourceType, ?int $sourceId, string $description, bool $spillover, bool $evidenceLinked): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO heat_logs (
                    user_id,
                    target_type,
                    target_id,
                    amount,
                    category,
                    source_type,
                    source_id,
                    description,
                    can_spillover,
                    evidence_linked,
                    game_date,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_FORMAT(NOW(), '%Y-%m-%d'), NOW())
            SQL
        )->execute([
            $userId,
            $targetType,
            $targetId,
            $amount,
            $category,
            $sourceType,
            $sourceId,
            $description,
            $spillover ? 1 : 0,
            $evidenceLinked ? 1 : 0,
        ]);
    }
}
