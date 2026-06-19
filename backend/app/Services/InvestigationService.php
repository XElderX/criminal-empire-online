<?php

namespace App\Services;

use App\Core\Database;

final class InvestigationService
{
    public function openOrAdvance(
        ?int $userId,
        string $targetType,
        ?int $targetId,
        int $heatAmount,
        string $sourceType,
        ?int $sourceId,
        string $description
    ): ?array {
        $threshold = match ($targetType) {
            'crew', 'boss' => 25,
            'gang' => 35,
            'district' => 50,
            default => 40,
        };

        if ($heatAmount < $threshold) {
            return null;
        }

        $pdo = Database::pdo();
        $statement = $pdo->prepare(
            <<<'SQL'
                SELECT *
                FROM police_investigations
                WHERE target_type = ?
                  AND (target_id <=> ?)
                  AND (user_id <=> ?)
                  AND status IN ('open','monitoring','active','escalated','raid_pending','arrest_pending')
                ORDER BY id DESC
                LIMIT 1
            SQL
        );
        $statement->execute([$targetType, $targetId, $userId]);
        $existing = $statement->fetch();

        if ($existing) {
            $suspicion = min(100, (int) $existing['suspicion'] + max(3, (int) floor($heatAmount / 8)));
            $evidence = min(100, (int) $existing['evidence_strength'] + max(2, (int) floor($heatAmount / 10)));
            $status = $this->statusFor($suspicion, $evidence);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE police_investigations
                    SET
                        suspicion = ?,
                        evidence_strength = ?,
                        investigation_level = GREATEST(investigation_level, ?),
                        status = ?,
                        last_advanced_at = NOW(),
                        notes = CONCAT(COALESCE(notes, ''), '\n', ?),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $suspicion,
                $evidence,
                $this->levelFor($suspicion, $evidence),
                $status,
                $description,
                $existing['id'],
            ]);

            $existing['suspicion'] = $suspicion;
            $existing['evidence_strength'] = $evidence;
            $existing['status'] = $status;
            $this->event((int) $existing['id'], $userId, 'investigation_advanced', 'Investigation advanced', $description, $targetType, $targetId, 'medium');

            return $existing;
        }

        $suspicion = min(100, max(10, (int) floor($heatAmount * 0.8)));
        $evidence = min(100, max(5, (int) floor($heatAmount * 0.45)));
        $status = $this->statusFor($suspicion, $evidence);

        $pdo->prepare(
            <<<'SQL'
                INSERT INTO police_investigations (
                    user_id,
                    target_type,
                    target_id,
                    status,
                    suspicion,
                    evidence_strength,
                    investigation_level,
                    known_associates,
                    lead_officer,
                    notes,
                    opened_at,
                    last_advanced_at,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, JSON_ARRAY(), ?, ?, NOW(), NOW(), NOW(), NOW())
            SQL
        )->execute([
            $userId,
            $targetType,
            $targetId,
            $status,
            $suspicion,
            $evidence,
            $this->levelFor($suspicion, $evidence),
            $this->leadOfficerName($targetType),
            $description,
        ]);

        $id = (int) $pdo->lastInsertId();
        $this->event($id, $userId, 'investigation_opened', 'Police opened an investigation', $description, $targetType, $targetId, 'medium');

        return $this->find($id);
    }

    public function listForUser(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM police_investigations
                WHERE user_id = ?
                ORDER BY FIELD(status, 'arrest_pending','raid_pending','escalated','active','monitoring','open','cold','resolved','closed'), suspicion DESC, id DESC
                LIMIT 80
            SQL
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function detail(int $userId, int $id): array
    {
        $investigation = $this->owned($userId, $id);
        $events = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM police_events
                WHERE investigation_id = ?
                ORDER BY id DESC
            SQL
        );
        $events->execute([$id]);

        return [
            'investigation' => $investigation,
            'events' => $events->fetchAll(),
        ];
    }

    public function respond(int $userId, int $id, string $responseCode): array
    {
        $investigation = $this->owned($userId, $id);
        $reduction = match ($responseCode) {
            'lawyer' => 12,
            'stay_silent' => 5,
            'cooperate_lightly' => 8,
            default => 0,
        };

        if ($reduction <= 0) {
            throw new \RuntimeException('Invalid investigation response.');
        }

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE police_investigations
                SET
                    suspicion = GREATEST(0, suspicion - ?),
                    evidence_strength = GREATEST(0, evidence_strength - FLOOR(? / 2)),
                    status = IF(suspicion - ? <= 15, 'cold', status),
                    updated_at = NOW()
                WHERE id = ?
                  AND user_id = ?
            SQL
        )->execute([$reduction, $reduction, $reduction, $id, $userId]);

        $this->event($id, $userId, 'investigation_response', 'Investigation response filed', "Response used: {$responseCode}.", $investigation['target_type'], $investigation['target_id'], 'low');

        return $this->detail($userId, $id);
    }

    public function advanceOpenInvestigations(int $userId): array
    {
        $pdo = Database::pdo();
        $statement = $pdo->prepare(
            <<<'SQL'
                SELECT *
                FROM police_investigations
                WHERE user_id = ?
                  AND status IN ('open','monitoring','active','escalated')
                FOR UPDATE
            SQL
        );
        $statement->execute([$userId]);
        $rows = $statement->fetchAll();
        $advanced = 0;
        $events = [];

        foreach ($rows as $row) {
            $suspicion = min(100, (int) $row['suspicion'] + 3);
            $evidence = min(100, (int) $row['evidence_strength'] + 2);
            $status = $this->statusFor($suspicion, $evidence);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE police_investigations
                    SET suspicion = ?, evidence_strength = ?, status = ?, investigation_level = ?, last_advanced_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$suspicion, $evidence, $status, $this->levelFor($suspicion, $evidence), $row['id']]);

            $events[] = $this->event((int) $row['id'], $userId, 'daily_investigation_tick', 'Investigation pressure increased', 'Police followed up on existing evidence.', $row['target_type'], $row['target_id'], $status === 'escalated' ? 'high' : 'medium');
            $advanced++;
        }

        return [
            'advanced' => $advanced,
            'events' => $events,
        ];
    }

    public function reducePressure(int $userId, int $amount, ?int $investigationId = null): int
    {
        $amount = max(0, $amount);
        if ($amount <= 0) {
            return 0;
        }

        if ($investigationId !== null) {
            $statement = Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE police_investigations
                    SET suspicion = GREATEST(0, suspicion - ?), evidence_strength = GREATEST(0, evidence_strength - FLOOR(? / 2)), updated_at = NOW()
                    WHERE id = ? AND user_id = ? AND status NOT IN ('closed','resolved')
                SQL
            );
            $statement->execute([$amount, $amount, $investigationId, $userId]);

            return $statement->rowCount();
        }

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE police_investigations
                SET suspicion = GREATEST(0, suspicion - ?), evidence_strength = GREATEST(0, evidence_strength - FLOOR(? / 2)), status = IF(suspicion - ? <= 10, 'cold', status), updated_at = NOW()
                WHERE user_id = ? AND status NOT IN ('closed','resolved')
            SQL
        );
        $statement->execute([$amount, $amount, $amount, $userId]);

        return $statement->rowCount();
    }

    public function find(int $id): ?array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM police_investigations WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private function owned(int $userId, int $id): array
    {
        $statement = Database::pdo()->prepare('SELECT * FROM police_investigations WHERE id = ? AND user_id = ? LIMIT 1');
        $statement->execute([$id, $userId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new \RuntimeException('Investigation not found.');
        }

        return $row;
    }

    private function event(int $investigationId, ?int $userId, string $code, string $title, string $description, ?string $targetType, mixed $targetId, string $severity): array
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO police_events (
                    user_id,
                    investigation_id,
                    event_code,
                    title,
                    description,
                    severity,
                    target_type,
                    target_id,
                    result,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, JSON_OBJECT(), NOW())
            SQL
        )->execute([$userId, $investigationId, $code, $title, $description, $severity, $targetType, $targetId]);

        return [
            'event_code' => $code,
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
        ];
    }

    private function statusFor(int $suspicion, int $evidence): string
    {
        return match (true) {
            $suspicion >= 90 || $evidence >= 85 => 'arrest_pending',
            $suspicion >= 75 || $evidence >= 70 => 'raid_pending',
            $suspicion >= 60 || $evidence >= 55 => 'escalated',
            $suspicion >= 40 || $evidence >= 35 => 'active',
            $suspicion >= 20 || $evidence >= 15 => 'monitoring',
            default => 'open',
        };
    }

    private function levelFor(int $suspicion, int $evidence): int
    {
        return max(1, min(5, (int) ceil(max($suspicion, $evidence) / 20)));
    }

    private function leadOfficerName(string $targetType): string
    {
        return match ($targetType) {
            'crew' => 'Detective Rowe',
            'gang' => 'Organized Crime Unit',
            'district' => 'District Patrol Bureau',
            'warehouse' => 'Inspection Detail',
            default => 'Detective Vale',
        };
    }
}
