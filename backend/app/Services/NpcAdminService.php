<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class NpcAdminService
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function list(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'npc.status = ?';
            $params[] = (string) $filters['status'];
        }

        if (($filters['alive'] ?? '') !== '') {
            $where[] = 'npc.alive = ?';
            $params[] = (int) $filters['alive'];
        }

        if (!empty($filters['role'])) {
            $where[] = 'npc.role = ?';
            $params[] = (string) $filters['role'];
        }

        if (!empty($filters['district'])) {
            $where[] = 'territory.name LIKE ?';
            $params[] = '%' . (string) $filters['district'] . '%';
        }

        if (!empty($filters['flag'])) {
            $flagColumn = match ((string) $filters['flag']) {
                'contact' => 'npc.is_contact',
                'witness' => 'npc.is_witness',
                'police' => 'npc.is_police',
                'rival' => 'npc.is_rival',
                'informant' => 'npc.is_informant',
                'recruitable' => 'npc.is_recruitable',
                default => null,
            };

            if ($flagColumn !== null) {
                $where[] = "{$flagColumn} = 1";
            }
        }

        if (!empty($filters['search'])) {
            $where[] = "CONCAT_WS(' ', npc.first_name, npc.last_name, npc.nickname, npc.notes, npc.organization, npc.affiliation, territory.name) LIKE ?";
            $params[] = '%' . (string) $filters['search'] . '%';
        }

        $orderBy = match ((string) ($filters['sort'] ?? 'last_seen')) {
            'name' => 'npc.first_name ASC, npc.last_name ASC',
            'age' => 'npc.age DESC',
            'status' => 'npc.status ASC, npc.alive DESC',
            'district' => 'territory.name ASC, npc.first_name ASC',
            'money' => 'npc.personal_cash DESC',
            'reputation' => 'npc.reputation DESC',
            'created' => 'npc.id DESC',
            default => 'npc.last_seen_at DESC, npc.id DESC',
        };

        $limit = min(100, max(10, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $pdo = Database::pdo();

        $count = $pdo->prepare(
            "SELECT COUNT(*) FROM npcs npc LEFT JOIN territories territory ON territory.id = npc.home_territory_id {$whereSql}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $statement = $pdo->prepare(
            <<<SQL
                SELECT
                    npc.*,
                    territory.name AS territory_name,
                    business.name AS business_name
                FROM npcs npc
                LEFT JOIN territories territory ON territory.id = npc.home_territory_id
                LEFT JOIN businesses business ON business.id = npc.workplace_business_id
                {$whereSql}
                ORDER BY {$orderBy}
                LIMIT {$limit} OFFSET {$offset}
            SQL
        );
        $statement->execute($params);
        $rows = $statement->fetchAll();
        $portraitResolver = new CrewPortraitResolver();
        $stageResolver = new CrewAgeStageResolver();

        foreach ($rows as &$row) {
            $row = $this->formatNpc($row, $portraitResolver, $stageResolver);
        }

        return [
            'data' => $rows,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'filters' => [
                'statuses' => $this->distinct('status'),
                'roles' => $this->distinct('role'),
                'districts' => $this->districts(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT npc.*, territory.name AS territory_name, business.name AS business_name
                FROM npcs npc
                LEFT JOIN territories territory ON territory.id = npc.home_territory_id
                LEFT JOIN businesses business ON business.id = npc.workplace_business_id
                WHERE npc.id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$npcId]);
        $npc = $statement->fetch();

        if (!$npc) {
            throw new RuntimeException('NPC not found.');
        }

        $portraitResolver = new CrewPortraitResolver();
        $stageResolver = new CrewAgeStageResolver();
        $npc = $this->formatNpc($npc, $portraitResolver, $stageResolver);

        $npc['relationships'] = $this->relationships($npcId);
        $npc['timeline'] = $this->timeline($npcId);
        $npc['crime_involvement'] = $this->crimeInvolvement($npcId);
        $npc['status_logs'] = $this->statusLogs($npcId);

        return ['npc' => $npc];
    }

    /** @return array<string, mixed> */
    private function formatNpc(
        array $row,
        CrewPortraitResolver $portraitResolver,
        CrewAgeStageResolver $stageResolver
    ): array {
        $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $row['display_name'] = $row['nickname'] ?: $row['full_name'];
        $row['portrait'] = $portraitResolver->resolve($row);
        $row['life_stage'] = $stageResolver->resolve((int) $row['age']);
        $row['is_dead'] = (int) ($row['alive'] ?? 1) === 0 || ($row['status'] ?? '') === 'dead';
        $row['flags'] = [
            'contact' => (bool) $row['is_contact'],
            'recruitable' => (bool) $row['is_recruitable'],
            'witness' => (bool) $row['is_witness'],
            'informant' => (bool) $row['is_informant'],
            'police' => (bool) $row['is_police'],
            'rival' => (bool) $row['is_rival'],
        ];
        $row['stats'] = [
            'strength' => (int) $row['strength'],
            'shooting' => (int) $row['shooting'],
            'driving' => (int) $row['driving'],
            'intelligence' => (int) $row['intelligence'],
            'stealth' => (int) $row['stealth'],
            'intimidation' => (int) $row['intimidation'],
            'discipline' => (int) $row['discipline'],
            'street_knowledge' => (int) $row['street_knowledge'],
            'endurance' => (int) $row['endurance'],
            'reliability' => (int) $row['reliability'],
            'courage' => (int) $row['courage'],
            'greed' => (int) $row['greed'],
        ];

        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    private function relationships(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT relationship.*, user.username
                FROM npc_relationships relationship
                JOIN users user ON user.id = relationship.user_id
                WHERE relationship.npc_id = ?
                ORDER BY relationship.updated_at DESC
            SQL
        );
        $statement->execute([$npcId]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    private function timeline(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM npc_timeline_events
                WHERE npc_id = ?
                ORDER BY id DESC
                LIMIT 100
            SQL
        );
        $statement->execute([$npcId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['metadata'] = $this->decodeJson($row['metadata']);
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function crimeInvolvement(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT involvement.*, run.outcome, run.status, opportunity.title
                FROM crime_npc_involvement involvement
                JOIN crime_runs run ON run.id = involvement.run_id
                JOIN crime_opportunities opportunity ON opportunity.id = run.opportunity_id
                WHERE involvement.npc_id = ?
                ORDER BY involvement.id DESC
                LIMIT 100
            SQL
        );
        $statement->execute([$npcId]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    private function statusLogs(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM npc_status_logs
                WHERE npc_id = ?
                ORDER BY id DESC
                LIMIT 100
            SQL
        );
        $statement->execute([$npcId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['metadata'] = $this->decodeJson($row['metadata']);
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function distinct(string $column): array
    {
        $allowed = ['status', 'role'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }

        $rows = Database::pdo()->query("SELECT DISTINCT {$column} AS value FROM npcs ORDER BY {$column}")->fetchAll();

        return array_map(static fn (array $row): string => (string) $row['value'], $rows);
    }

    /** @return array<int, string> */
    private function districts(): array
    {
        $rows = Database::pdo()->query('SELECT name FROM territories ORDER BY name')->fetchAll();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
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
