<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class RecruitmentService
{
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

        foreach ($candidates as &$candidate) {
            $candidate['traits'] = $this->traits((int) $candidate['npc_id']);
            $candidate['can_hire'] = (int) $user['cash'] >= (int) $candidate['recruitment_fee']
                && (int) ($user['reputation'] ?? 0) >= (int) $candidate['reputation_required'];
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
                        npc.nickname
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
