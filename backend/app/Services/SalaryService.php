<?php

namespace App\Services;

use App\Core\Database;
use Throwable;

final class SalaryService
{
    public function processDue(): array
    {
        $members = Database::pdo()->query(
            <<<'SQL'
                SELECT
                    member.*,
                    npc.personal_cash,
                    npc.expenses_weekly
                FROM player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                WHERE member.status NOT IN ('dismissed', 'dead')
                  AND (
                    member.last_salary_at IS NULL
                    OR member.last_salary_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  )
                ORDER BY member.id
            SQL
        )->fetchAll();

        $summary = [
            'members_processed' => count($members),
            'salary_paid' => 0,
            'missed_payments' => 0,
            'personal_expenses' => 0,
        ];

        foreach ($members as $member) {
            $result = $this->processMember($member);
            $summary['salary_paid'] += $result['salary_paid'];
            $summary['personal_expenses'] += $result['personal_expenses'];

            if ($result['unpaid_added'] > 0) {
                $summary['missed_payments']++;
            }
        }

        return $summary;
    }

    private function processMember(array $member): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $userCash = $this->lockUserCash((int) $member['user_id']);
            $npc = $this->lockNpc((int) $member['npc_id']);
            $amountDue = (int) $member['salary_weekly'];
            $amountPaid = min($userCash, $amountDue);
            $unpaidAdded = $amountDue - $amountPaid;

            if ($amountPaid > 0) {
                $pdo->prepare(
                    <<<'SQL'
                        UPDATE users
                        SET cash = cash - ?, updated_at = NOW()
                        WHERE id = ?
                    SQL
                )->execute([
                    $amountPaid,
                    $member['user_id'],
                ]);

                $pdo->prepare(
                    <<<'SQL'
                        UPDATE npcs
                        SET personal_cash = personal_cash + ?, updated_at = NOW()
                        WHERE id = ?
                    SQL
                )->execute([
                    $amountPaid,
                    $member['npc_id'],
                ]);

                (new EconomyLedgerService())->record(
                    'salary_payment',
                    $amountPaid,
                    'Weekly crew salary payment.',
                    [
                        'source_type' => 'player',
                        'source_id' => $member['user_id'],
                        'destination_type' => 'gang_member',
                        'destination_id' => $member['id'],
                        'user_id' => $member['user_id'],
                        'npc_id' => $member['npc_id'],
                        'gang_member_id' => $member['id'],
                    ]
                );
            }

            $moraleChange = $unpaidAdded > 0 ? -8 : 3;
            $loyaltyChange = $unpaidAdded > 0 ? -5 : 2;

            $pdo->prepare(
                <<<'SQL'
                    UPDATE player_gang_members
                    SET
                        unpaid_salary = unpaid_salary + ?,
                        morale = GREATEST(0, LEAST(100, morale + ?)),
                        loyalty = GREATEST(0, LEAST(100, loyalty + ?)),
                        last_salary_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $unpaidAdded,
                $moraleChange,
                $loyaltyChange,
                $member['id'],
            ]);

            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO salary_payments (
                        gang_member_id,
                        user_id,
                        amount_due,
                        amount_paid,
                        unpaid_added,
                        processed_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                SQL
            )->execute([
                $member['id'],
                $member['user_id'],
                $amountDue,
                $amountPaid,
                $unpaidAdded,
            ]);

            $historyDescription = $unpaidAdded > 0
                ? sprintf(
                    'Received $%d; $%d remains unpaid.',
                    $amountPaid,
                    $unpaidAdded
                )
                : sprintf(
                    'Received the full weekly salary of $%d.',
                    $amountPaid
                );

            (new CrewHistoryService())->record(
                (int) $member['id'],
                (int) $member['user_id'],
                $unpaidAdded > 0 ? 'salary_missed' : 'salary_paid',
                $unpaidAdded > 0 ? 'Salary payment missed' : 'Weekly salary paid',
                $historyDescription,
                [
                    'amount_due' => $amountDue,
                    'amount_paid' => $amountPaid,
                    'unpaid_added' => $unpaidAdded,
                ]
            );

            $personalCashAfterSalary = (int) $npc['personal_cash'] + $amountPaid;
            $personalExpenses = min(
                $personalCashAfterSalary,
                max(0, (int) $npc['expenses_weekly'])
            );

            if ($personalExpenses > 0) {
                $pdo->prepare(
                    <<<'SQL'
                        UPDATE npcs
                        SET personal_cash = personal_cash - ?, updated_at = NOW()
                        WHERE id = ?
                    SQL
                )->execute([
                    $personalExpenses,
                    $member['npc_id'],
                ]);

                (new EconomyLedgerService())->record(
                    'personal_expense',
                    $personalExpenses,
                    'Crew member weekly living expenses.',
                    [
                        'source_type' => 'gang_member',
                        'source_id' => $member['id'],
                        'destination_type' => 'npc_economy_sink',
                        'user_id' => $member['user_id'],
                        'npc_id' => $member['npc_id'],
                        'gang_member_id' => $member['id'],
                    ]
                );
            }

            $pdo->commit();

            return [
                'salary_paid' => $amountPaid,
                'unpaid_added' => $unpaidAdded,
                'personal_expenses' => $personalExpenses,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function lockUserCash(int $userId): int
    {
        $statement = Database::pdo()->prepare(
            'SELECT cash FROM users WHERE id = ? FOR UPDATE'
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    private function lockNpc(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM npcs WHERE id = ? FOR UPDATE'
        );
        $statement->execute([$npcId]);

        return $statement->fetch() ?: [
            'personal_cash' => 0,
            'expenses_weekly' => 0,
        ];
    }
}
