<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class RecruitmentService
{
    public function refreshCandidatePool(): array
    {
        $pdo = Database::pdo();

        $expired = $pdo->exec(
            <<<'SQL'
                UPDATE recruitment_candidates
                SET status = 'expired'
                WHERE status = 'available'
                  AND expires_at IS NOT NULL
                  AND expires_at <= NOW()
            SQL
        );

        $refreshed = $pdo->exec(
            <<<'SQL'
                UPDATE recruitment_candidates candidate
                LEFT JOIN player_gang_members member
                    ON member.recruitment_candidate_id = candidate.id
                    AND member.status <> 'dismissed'
                SET
                    candidate.status = 'available',
                    candidate.available_from = NOW(),
                    candidate.expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
                    candidate.hired_by_user_id = NULL,
                    candidate.hired_at = NULL
                WHERE candidate.status = 'expired'
                  AND member.id IS NULL
            SQL
        );

        $spawned = $this->spawnNewCandidates(
            GameConfig::RECRUITMENT_CANDIDATE_TARGET
                - $this->availableCandidateCount()
        );

        $available = (int) $pdo->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM recruitment_candidates
                WHERE status = 'available'
                  AND available_from <= NOW()
                  AND (expires_at IS NULL OR expires_at > NOW())
            SQL
        )->fetchColumn();

        return [
            'expired' => (int) $expired,
            'refreshed' => (int) $refreshed,
            'spawned' => $spawned,
            'available' => $available,
        ];
    }

    public function candidates(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    candidate.*,
                    npc.first_name,
                    npc.last_name,
                    npc.nickname,
                    npc.age,
                    npc.gender,
                    npc.portrait_set_key,
                    npc.portrait_stage_cache,
                    npc.portrait_focal_x,
                    npc.portrait_focal_y,
                    npc.reputation AS npc_reputation,
                    npc.criminal_reputation,
                    npc.biography,
                    npc.background,
                    npc.occupation,
                    npc.personal_cash,
                    npc.health,
                    npc.max_health,
                    npc.morale,
                    npc.loyalty,
                    territory.name AS territory_name
                FROM recruitment_candidates candidate
                JOIN npcs npc ON npc.id = candidate.npc_id
                JOIN territories territory ON territory.id = candidate.territory_id
                WHERE candidate.status = 'available'
                  AND candidate.available_from <= NOW()
                  AND (
                    candidate.expires_at IS NULL
                    OR candidate.expires_at > NOW()
                  )
                ORDER BY candidate.recruitment_fee, candidate.id
            SQL
        );
        $statement->execute();
        $candidates = $statement->fetchAll();
        $crewCountStatement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM player_gang_members
                WHERE user_id = ?
                  AND status <> 'dismissed'
            SQL
        );
        $crewCountStatement->execute([$user['id']]);
        $crewCapacityReached = (int) $crewCountStatement->fetchColumn()
            >= GameConfig::MAX_GANG_MEMBERS;

        $usedPortraitKeys = [];

        foreach ($candidates as &$candidate) {
            $candidate = $this->ensurePortrait(
                $candidate,
                $usedPortraitKeys
            );
            $usedPortraitKeys[] = (string) $candidate['portrait_set_key'];
            $candidate['traits'] = $this->traits((int) $candidate['npc_id']);
            $candidate = (new CrewPresentationService())->present($candidate);

            $hasEnoughCash = (int) $user['cash']
                >= (int) $candidate['recruitment_fee'];
            $hasEnoughReputation = (int) ($user['reputation'] ?? 0)
                >= (int) $candidate['reputation_required'];
            $isRecruitableAge = (bool) $candidate['life_stage']['recruitable'];

            $candidate['can_hire'] = $hasEnoughCash
                && $hasEnoughReputation
                && $isRecruitableAge
                && !$crewCapacityReached;
            $candidate['hire_block_reasons'] = $this->hireBlockReasons(
                $user,
                $candidate,
                $crewCapacityReached
            );
        }

        return $candidates;
    }

    public function hire(array $user, int $candidateId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $candidateStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT
                        candidate.*,
                        npc.personal_cash,
                        npc.morale,
                        npc.loyalty,
                        npc.health,
                        npc.max_health,
                        npc.first_name,
                        npc.last_name,
                        npc.nickname,
                        npc.age,
                        npc.gender
                    FROM recruitment_candidates candidate
                    JOIN npcs npc ON npc.id = candidate.npc_id
                    WHERE candidate.id = ?
                    FOR UPDATE
                SQL
            );
            $candidateStatement->execute([$candidateId]);
            $candidate = $candidateStatement->fetch();

            if (!$candidate || $candidate['status'] !== 'available') {
                throw new RuntimeException('Candidate is no longer available.');
            }

            if (
                $candidate['expires_at'] !== null
                && strtotime($candidate['expires_at']) <= time()
            ) {
                throw new RuntimeException('Candidate is no longer available.');
            }

            $lifeStage = (new CrewAgeStageResolver())->resolve(
                (int) $candidate['age']
            );

            if (!(bool) $lifeStage['recruitable']) {
                throw new RuntimeException(
                    'Candidate is outside the recruitable age range.'
                );
            }

            (new PortraitAssignmentService())->assignToNpc(
                (int) $candidate['npc_id']
            );

            $userStatement = $pdo->prepare(
                'SELECT * FROM users WHERE id = ? FOR UPDATE'
            );
            $userStatement->execute([$user['id']]);
            $freshUser = $userStatement->fetch();

            if ((int) $freshUser['cash'] < (int) $candidate['recruitment_fee']) {
                throw new RuntimeException('Not enough cash.');
            }

            if (
                (int) ($freshUser['reputation'] ?? 0)
                < (int) $candidate['reputation_required']
            ) {
                throw new RuntimeException('Not enough reputation.');
            }

            $countStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT COUNT(*)
                    FROM player_gang_members
                    WHERE user_id = ?
                      AND status <> 'dismissed'
                SQL
            );
            $countStatement->execute([$freshUser['id']]);

            if ((int) $countStatement->fetchColumn() >= GameConfig::MAX_GANG_MEMBERS) {
                throw new RuntimeException('Crew capacity reached.');
            }

            $pdo->prepare(
                'UPDATE users SET cash = cash - ?, updated_at = NOW() WHERE id = ?'
            )->execute([
                $candidate['recruitment_fee'],
                $freshUser['id'],
            ]);

            $existingMemberStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT *
                    FROM player_gang_members
                    WHERE npc_id = ?
                    FOR UPDATE
                SQL
            );
            $existingMemberStatement->execute([$candidate['npc_id']]);
            $existingMember = $existingMemberStatement->fetch();

            if ($existingMember && $existingMember['status'] !== 'dismissed') {
                throw new RuntimeException(
                    'This NPC is already attached to an active crew record.'
                );
            }

            if ($existingMember) {
                $memberId = (int) $existingMember['id'];
                $this->reactivateDismissedMember(
                    $freshUser,
                    $candidate,
                    $memberId
                );
            } else {
                $memberId = $this->createMemberFromCandidate(
                    $freshUser,
                    $candidate
                );
            }

            $pdo->prepare(
                <<<'SQL'
                    UPDATE recruitment_candidates
                    SET
                        status = 'hired',
                        hired_by_user_id = ?,
                        hired_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$freshUser['id'], $candidate['id']]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE npcs
                    SET
                        role = 'gang_member',
                        status = 'employed',
                        personal_cash = personal_cash + ?,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $candidate['recruitment_fee'],
                $candidate['npc_id'],
            ]);

            $displayName = trim(
                $candidate['first_name']
                . ' '
                . ($candidate['nickname'] ? "“{$candidate['nickname']}” " : '')
                . $candidate['last_name']
            );

            $historyTitle = $existingMember
                ? 'Rejoined the crew'
                : 'Joined the crew';
            $historyDescription = $existingMember
                ? "{$displayName} returned to active crew work."
                : "{$displayName} was recruited as a street-level crew member.";

            (new CrewHistoryService())->record(
                $memberId,
                (int) $freshUser['id'],
                'recruited',
                $historyTitle,
                $historyDescription,
                [
                    'candidate_id' => $candidate['id'],
                    'recruitment_fee' => (int) $candidate['recruitment_fee'],
                    'salary_weekly' => (int) $candidate['salary_weekly'],
                ]
            );

            (new EconomyLedgerService())->record(
                'recruitment_fee',
                (int) $candidate['recruitment_fee'],
                'Recruitment fee transferred to the NPC recruit',
                [
                    'source_type' => 'player',
                    'source_id' => $freshUser['id'],
                    'destination_type' => 'npc',
                    'destination_id' => $candidate['npc_id'],
                    'user_id' => $freshUser['id'],
                    'npc_id' => $candidate['npc_id'],
                    'gang_member_id' => $memberId,
                    'territory_id' => $candidate['territory_id'],
                ]
            );

            AuditService::log(
                (int) $freshUser['id'],
                'crew.hire',
                [
                    'candidate_id' => $candidate['id'],
                    'member_id' => $memberId,
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Crew member hired.',
                'gang_member_id' => $memberId,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function createMemberFromCandidate(
        array $user,
        array $candidate
    ): int {
        $insert = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO player_gang_members (
                    user_id,
                    npc_id,
                    recruitment_candidate_id,
                    salary_weekly,
                    personal_expenses_weekly,
                    strength,
                    shooting,
                    driving,
                    intelligence,
                    stealth,
                    intimidation,
                    discipline,
                    street_knowledge,
                    endurance,
                    level,
                    experience,
                    health,
                    max_health,
                    morale,
                    loyalty,
                    status,
                    recruited_at,
                    last_salary_at,
                    created_at,
                    updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'active', NOW(), NOW(), NOW(), NOW()
                )
            SQL
        );

        $insert->execute([
            $user['id'],
            $candidate['npc_id'],
            $candidate['id'],
            $candidate['salary_weekly'],
            $candidate['personal_expenses_weekly'],
            $candidate['strength'],
            $candidate['shooting'],
            $candidate['driving'],
            $candidate['intelligence'],
            $candidate['stealth'],
            $candidate['intimidation'],
            $candidate['discipline'],
            $candidate['street_knowledge'],
            $candidate['endurance'],
            $candidate['level'],
            $candidate['experience'],
            $candidate['health'],
            $candidate['max_health'],
            $candidate['morale'],
            $candidate['loyalty'],
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    private function reactivateDismissedMember(
        array $user,
        array $candidate,
        int $memberId
    ): void {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE player_gang_members
                SET
                    user_id = ?,
                    recruitment_candidate_id = ?,
                    salary_weekly = ?,
                    personal_expenses_weekly = ?,
                    strength = ?,
                    shooting = ?,
                    driving = ?,
                    intelligence = ?,
                    stealth = ?,
                    intimidation = ?,
                    discipline = ?,
                    street_knowledge = ?,
                    endurance = ?,
                    level = GREATEST(level, ?),
                    experience = GREATEST(experience, ?),
                    health = ?,
                    max_health = ?,
                    morale = ?,
                    loyalty = ?,
                    status = 'active',
                    current_assignment_type = NULL,
                    current_assignment_id = NULL,
                    recovering_until = NULL,
                    arrested_until = NULL,
                    recruited_at = NOW(),
                    last_salary_at = NOW(),
                    dismissed_at = NULL,
                    dismissal_reason = NULL,
                    updated_at = NOW()
                WHERE id = ?
            SQL
        );

        $statement->execute([
            $user['id'],
            $candidate['id'],
            $candidate['salary_weekly'],
            $candidate['personal_expenses_weekly'],
            $candidate['strength'],
            $candidate['shooting'],
            $candidate['driving'],
            $candidate['intelligence'],
            $candidate['stealth'],
            $candidate['intimidation'],
            $candidate['discipline'],
            $candidate['street_knowledge'],
            $candidate['endurance'],
            $candidate['level'],
            $candidate['experience'],
            $candidate['health'],
            $candidate['max_health'],
            $candidate['morale'],
            $candidate['loyalty'],
            $memberId,
        ]);
    }

    public function payOverdue(array $user, int $memberId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $memberStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT member.*, npc.personal_cash
                    FROM player_gang_members member
                    JOIN npcs npc ON npc.id = member.npc_id
                    WHERE member.id = ?
                      AND member.user_id = ?
                    FOR UPDATE
                SQL
            );
            $memberStatement->execute([$memberId, $user['id']]);
            $member = $memberStatement->fetch();

            if (!$member) {
                throw new RuntimeException('Crew member not found.');
            }

            $amount = (int) $member['unpaid_salary'];

            if ($amount <= 0) {
                throw new RuntimeException('No overdue salary is due.');
            }

            $userStatement = $pdo->prepare(
                'SELECT cash FROM users WHERE id = ? FOR UPDATE'
            );
            $userStatement->execute([$user['id']]);
            $cash = (int) $userStatement->fetchColumn();

            if ($cash < $amount) {
                throw new RuntimeException('Not enough cash.');
            }

            $pdo->prepare(
                'UPDATE users SET cash = cash - ?, updated_at = NOW() WHERE id = ?'
            )->execute([$amount, $user['id']]);

            $pdo->prepare(
                'UPDATE npcs SET personal_cash = personal_cash + ?, updated_at = NOW() WHERE id = ?'
            )->execute([$amount, $member['npc_id']]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE player_gang_members
                    SET
                        unpaid_salary = 0,
                        morale = LEAST(100, morale + 8),
                        loyalty = LEAST(100, loyalty + 5),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$memberId]);

            (new CrewHistoryService())->record(
                $memberId,
                (int) $user['id'],
                'salary',
                'Overdue salary paid',
                "The player settled {$amount} dollars in overdue salary.",
                ['amount' => $amount]
            );

            (new EconomyLedgerService())->record(
                'salary_payment',
                $amount,
                'Overdue salary paid',
                [
                    'source_type' => 'player',
                    'source_id' => $user['id'],
                    'destination_type' => 'gang_member',
                    'destination_id' => $memberId,
                    'user_id' => $user['id'],
                    'npc_id' => $member['npc_id'],
                    'gang_member_id' => $memberId,
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Overdue salary paid.',
                'amount' => $amount,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<int, string> $excludedKeys
     * @return array<string, mixed>
     */
    private function ensurePortrait(
        array $candidate,
        array $excludedKeys
    ): array {
        if (empty($candidate['portrait_set_key'])) {
            $npc = (new PortraitAssignmentService())->assignToNpc(
                (int) $candidate['npc_id'],
                $excludedKeys
            );

            foreach ([
                'gender',
                'portrait_set_key',
                'portrait_stage_cache',
                'portrait_focal_x',
                'portrait_focal_y',
            ] as $field) {
                $candidate[$field] = $npc[$field] ?? null;
            }
        }

        return $candidate;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $candidate
     * @return array<int, string>
     */
    private function hireBlockReasons(
        array $user,
        array $candidate,
        bool $crewCapacityReached
    ): array {
        $reasons = [];

        if ((int) $user['cash'] < (int) $candidate['recruitment_fee']) {
            $reasons[] = 'Insufficient cash.';
        }

        if (
            (int) ($user['reputation'] ?? 0)
            < (int) $candidate['reputation_required']
        ) {
            $reasons[] = 'Insufficient reputation.';
        }

        if (!(bool) ($candidate['life_stage']['recruitable'] ?? true)) {
            $reasons[] = 'Candidate is outside the recruitable age range.';
        }

        if ($crewCapacityReached) {
            $reasons[] = 'Crew capacity reached.';
        }

        return $reasons;
    }

    private function availableCandidateCount(): int
    {
        return (int) Database::pdo()->query(
            <<<'SQL'
                SELECT COUNT(*)
                FROM recruitment_candidates
                WHERE status = 'available'
                  AND available_from <= NOW()
                  AND (expires_at IS NULL OR expires_at > NOW())
            SQL
        )->fetchColumn();
    }

    private function spawnNewCandidates(int $needed): int
    {
        if ($needed <= 0) {
            return 0;
        }

        $pdo = Database::pdo();
        $statement = $pdo->prepare(
            <<<'SQL'
                SELECT
                    npc.id,
                    npc.home_territory_id,
                    npc.reputation,
                    npc.criminal_reputation,
                    npc.age,
                    npc.strength,
                    npc.shooting,
                    npc.driving,
                    npc.intelligence,
                    npc.stealth,
                    npc.intimidation,
                    npc.discipline,
                    npc.street_knowledge,
                    npc.endurance
                FROM npcs npc
                LEFT JOIN recruitment_candidates candidate
                    ON candidate.npc_id = npc.id
                LEFT JOIN player_gang_members member
                    ON member.npc_id = npc.id
                   AND member.status <> 'dismissed'
                WHERE (
                    npc.role = 'recruit'
                    OR COALESCE(npc.is_recruitable, 0) = 1
                )
                  AND npc.alive = 1
                  AND npc.status = 'active'
                  AND npc.arrested_until IS NULL
                  AND COALESCE(npc.is_contact, 0) = 0
                  AND COALESCE(npc.is_informant, 0) = 0
                  AND COALESCE(npc.is_witness, 0) = 0
                  AND COALESCE(npc.is_police, 0) = 0
                  AND COALESCE(npc.is_rival, 0) = 0
                  AND candidate.id IS NULL
                  AND member.id IS NULL
                ORDER BY npc.reputation DESC, npc.criminal_reputation DESC, npc.id
                LIMIT ?
            SQL
        );
        $statement->bindValue(1, $needed, \PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        $spawned = 0;

        foreach ($rows as $row) {
            $this->insertCandidateFromNpc($row);
            $spawned++;
        }

        $fallbackNeeded = $needed - $spawned;

        for ($index = 0; $index < $fallbackNeeded; $index++) {
            $npc = $this->createGeneratedRecruitableNpc();
            $this->insertCandidateFromNpc($npc);
            $spawned++;
        }

        return $spawned;
    }

    /**
     * @param array<string, mixed> $npc
     */
    private function insertCandidateFromNpc(array $npc): void
    {
        $stats = [
            (int) $npc['strength'],
            (int) $npc['shooting'],
            (int) $npc['driving'],
            (int) $npc['intelligence'],
            (int) $npc['stealth'],
            (int) $npc['intimidation'],
            (int) $npc['discipline'],
            (int) $npc['street_knowledge'],
            (int) $npc['endurance'],
        ];
        $average = (int) round(array_sum($stats) / max(1, count($stats)));
        $tier = match (true) {
            $average >= 78 => 'veteran',
            $average >= 64 => 'specialist',
            $average >= 50 => 'experienced',
            default => 'street',
        };
        $level = min(2, max(1, (int) round($average / 18)));
        $experience = max(0, ($level - 1) * 250);
        $reputationRequired = max(0, (int) floor(
            ((int) $npc['reputation'] + (int) $npc['criminal_reputation']) / 15
        ));
        $recruitmentFee = max(
            120,
            (int) round($average * 8 + $reputationRequired * 25)
        );
        $salaryWeekly = max(35, (int) round($recruitmentFee * 0.3));
        $personalExpensesWeekly = max(20, (int) round($salaryWeekly * 0.6));

        $insert = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO recruitment_candidates (
                    npc_id,
                    territory_id,
                    tier,
                    recruitment_fee,
                    salary_weekly,
                    personal_expenses_weekly,
                    reputation_required,
                    strength,
                    shooting,
                    driving,
                    intelligence,
                    stealth,
                    intimidation,
                    discipline,
                    street_knowledge,
                    endurance,
                    level,
                    experience,
                    available_from,
                    expires_at,
                    status,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'available', NOW()
                )
            SQL
        );

        $insert->execute([
            $npc['id'],
            $npc['home_territory_id'] ?? $this->randomTerritoryId(),
            $tier,
            $recruitmentFee,
            $salaryWeekly,
            $personalExpensesWeekly,
            $reputationRequired,
            $npc['strength'],
            $npc['shooting'],
            $npc['driving'],
            $npc['intelligence'],
            $npc['stealth'],
            $npc['intimidation'],
            $npc['discipline'],
            $npc['street_knowledge'],
            $npc['endurance'],
            $level,
            $experience,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createGeneratedRecruitableNpc(): array
    {
        $profiles = [
            ['first' => 'Arin', 'last' => 'Kade', 'nick' => 'Side Street', 'gender' => 'male'],
            ['first' => 'Mira', 'last' => 'Vale', 'nick' => 'Short Fuse', 'gender' => 'female'],
            ['first' => 'Jon', 'last' => 'Hale', 'nick' => 'Quickstep', 'gender' => 'male'],
            ['first' => 'Nina', 'last' => 'Rook', 'nick' => 'Low Key', 'gender' => 'female'],
            ['first' => 'Drew', 'last' => 'Cross', 'nick' => 'Night Shift', 'gender' => 'male'],
            ['first' => 'Tess', 'last' => 'Marlow', 'nick' => 'Back Alley', 'gender' => 'female'],
        ];
        $profile = $profiles[array_rand($profiles)];
        $territoryId = $this->randomTerritoryId();
        $personalCash = $this->randomNumber(20, 150);
        $stats = $this->generatedStats();

        $insert = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO npcs (
                    first_name,
                    last_name,
                    nickname,
                    age,
                    gender,
                    biography,
                    background,
                    occupation,
                    role,
                    home_territory_id,
                    personal_cash,
                    bank_cash,
                    income_weekly,
                    expenses_weekly,
                    wealth_class,
                    reputation,
                    criminal_reputation,
                    health,
                    max_health,
                    morale,
                    loyalty,
                    status,
                    alive,
                    is_contact,
                    is_informant,
                    is_witness,
                    is_police,
                    is_rival,
                    is_recruitable,
                    reliability,
                    courage,
                    greed,
                    strength,
                    shooting,
                    driving,
                    intelligence,
                    stealth,
                    intimidation,
                    discipline,
                    street_knowledge,
                    endurance,
                    source_event,
                    created_at,
                    updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 'recruit', ?, ?, 0, 0, 0, 'poor', ?, ?, 100, 100, 60, 50, 'active', 1, 0, 0, 0, 0, 0, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'world_recruit_refresh', NOW(), NOW()
                )
            SQL
        );

        $insert->execute([
            $profile['first'],
            $profile['last'],
            $profile['nick'],
            $this->randomNumber(19, 44),
            $profile['gender'],
            'A locally generated recruitable NPC created by world processing.',
            'A city resident whose life can now intersect with the crew.',
            'Unemployed local',
            $territoryId,
            $personalCash,
            $stats['reputation'],
            $stats['criminal_reputation'],
            $stats['reliability'],
            $stats['courage'],
            $stats['greed'],
            $stats['strength'],
            $stats['shooting'],
            $stats['driving'],
            $stats['intelligence'],
            $stats['stealth'],
            $stats['intimidation'],
            $stats['discipline'],
            $stats['street_knowledge'],
            $stats['endurance'],
        ]);

        $npcId = (int) Database::pdo()->lastInsertId();
        (new PortraitAssignmentService())->assignToNpc($npcId);

        $statement = Database::pdo()->prepare('SELECT * FROM npcs WHERE id = ? LIMIT 1');
        $statement->execute([$npcId]);

        return $statement->fetch() ?: [];
    }

    /**
     * @return array<string, int>
     */
    private function generatedStats(): array
    {
        return [
            'strength' => $this->randomNumber(22, 58),
            'shooting' => $this->randomNumber(18, 52),
            'driving' => $this->randomNumber(20, 60),
            'intelligence' => $this->randomNumber(24, 58),
            'stealth' => $this->randomNumber(22, 60),
            'intimidation' => $this->randomNumber(18, 54),
            'discipline' => $this->randomNumber(26, 60),
            'street_knowledge' => $this->randomNumber(24, 60),
            'endurance' => $this->randomNumber(24, 58),
            'reputation' => $this->randomNumber(0, 8),
            'criminal_reputation' => $this->randomNumber(0, 14),
            'reliability' => $this->randomNumber(35, 72),
            'courage' => $this->randomNumber(32, 70),
            'greed' => $this->randomNumber(28, 72),
        ];
    }

    private function randomTerritoryId(): int
    {
        $statement = Database::pdo()->query(
            'SELECT id FROM territories ORDER BY id ASC'
        );
        $territories = array_column($statement->fetchAll(), 'id');

        if ($territories === []) {
            return 1;
        }

        return (int) $territories[array_rand($territories)];
    }

    private function randomNumber(int $minimum, int $maximum): int
    {
        return random_int($minimum, $maximum);
    }

    private function traits(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    trait.code,
                    trait.name,
                    trait.polarity,
                    trait.description,
                    trait.effects
                FROM npc_trait_assignments assignment
                JOIN npc_traits trait ON trait.id = assignment.trait_id
                WHERE assignment.npc_id = ?
            SQL
        );
        $statement->execute([$npcId]);
        $traits = $statement->fetchAll();

        foreach ($traits as &$trait) {
            $trait['effects'] = json_decode((string) $trait['effects'], true) ?: [];
        }

        return $traits;
    }
}
