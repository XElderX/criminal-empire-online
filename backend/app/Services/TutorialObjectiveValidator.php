<?php

namespace App\Services;

use App\Core\Database;

final class TutorialObjectiveValidator
{
    public function isComplete(int $userId, array $step, bool $acknowledged): bool
    {
        $type = (string) ($step['objective_type'] ?? 'acknowledge');
        $payload = $step['objective_payload'] ?? [];

        return match ($type) {
            'acknowledge' => $acknowledged,
            'view_page' => $this->hasViewedPage($userId, (array) $payload),
            'travel_to_location' => $this->hasTravelled($userId, (array) $payload),
            'explore_hotspot' => $this->hasExploredHotspot($userId),
            'complete_job' => $this->hasCompletedStarterJob($userId),
            'complete_quick_crime' => $this->hasAttemptedQuickCrime($userId),
            'inspect_candidate' => $this->hasEvent($userId, 'inspect_candidate') || $this->hasViewedPage($userId, ['page' => 'recruitment']),
            'hire_crew' => $this->hasActiveNpcCrew($userId),
            'equip_item' => $this->hasEquipmentAssigned($userId),
            'inspect_dirty_job' => $this->hasEvent($userId, 'inspect_dirty_job') || $this->hasViewedPage($userId, ['page' => 'dirty jobs']),
            'execute_dirty_job' => $this->hasResolvedOrPreparedDirtyJob($userId),
            'view_heat_page' => $this->hasViewedPage($userId, ['page' => 'heat']),
            'view_territory' => $this->hasViewedPage($userId, ['page' => 'territories']) || $this->hasEvent($userId, 'view_territory'),
            'view_warehouse' => $this->hasViewedPage($userId, ['page' => 'warehouse']),
            'view_guide' => $this->hasViewedPage($userId, ['page' => 'guide']) || $acknowledged,
            default => false,
        };
    }

    private function hasViewedPage(int $userId, array $payload): bool
    {
        $page = (string) ($payload['page'] ?? '');

        if ($page === '') {
            return $this->hasEvent($userId, 'view_page');
        }

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM tutorial_objective_events
                WHERE user_id = ?
                  AND action_type = 'view_page'
                  AND page_key = ?
            SQL
        );
        $statement->execute([$userId, $page]);

        if ((int) $statement->fetchColumn() > 0) {
            return true;
        }

        return false;
    }

    private function hasTravelled(int $userId, array $payload): bool
    {
        $locationSlug = (string) ($payload['location_slug'] ?? '');
        $regionSlug = (string) ($payload['region_slug'] ?? '');

        if ($locationSlug === '' && $regionSlug === '') {
            return $this->count('SELECT COUNT(*) FROM user_travel_logs WHERE user_id = ?', [$userId]) > 0;
        }

        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM user_location_state state
            JOIN world_locations location ON location.id = state.current_location_id
            JOIN world_regions region ON region.id = state.current_region_id
            WHERE state.user_id = ?
        SQL;
        $params = [$userId];

        if ($locationSlug !== '') {
            $sql .= ' AND location.slug = ?';
            $params[] = $locationSlug;
        }

        if ($regionSlug !== '') {
            $sql .= ' AND region.slug = ?';
            $params[] = $regionSlug;
        }

        return $this->count($sql, $params) > 0;
    }

    private function hasExploredHotspot(int $userId): bool
    {
        if ($this->count('SELECT COUNT(*) FROM user_location_presence WHERE user_id = ? AND last_explored_at IS NOT NULL', [$userId]) > 0) {
            return true;
        }

        return $this->count('SELECT COUNT(*) FROM location_exploration_logs WHERE user_id = ?', [$userId]) > 0;
    }

    private function hasCompletedStarterJob(int $userId): bool
    {
        return $this->count(
            <<<'SQL'
                SELECT COUNT(*)
                FROM job_runs
                WHERE user_id = ?
                  AND status IN ('completed', 'failed')
            SQL,
            [$userId]
        ) > 0;
    }

    private function hasAttemptedQuickCrime(int $userId): bool
    {
        if ($this->count('SELECT COUNT(*) FROM quick_crime_runs WHERE user_id = ?', [$userId]) > 0) {
            return true;
        }

        return $this->count('SELECT COUNT(*) FROM crime_logs WHERE user_id = ?', [$userId]) > 0;
    }

    private function hasActiveNpcCrew(int $userId): bool
    {
        return $this->count(
            <<<'SQL'
                SELECT COUNT(*)
                FROM player_gang_members
                WHERE user_id = ?
                  AND status = 'active'
            SQL,
            [$userId]
        ) > 0;
    }

    private function hasEquipmentAssigned(int $userId): bool
    {
        return $this->count('SELECT COUNT(*) FROM crew_equipment WHERE user_id = ?', [$userId]) > 0;
    }

    private function hasResolvedOrPreparedDirtyJob(int $userId): bool
    {
        if ($this->count(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_runs
                WHERE user_id = ?
                  AND status IN ('completed', 'partially_completed', 'failed', 'active', 'executing')
            SQL,
            [$userId]
        ) > 0) {
            return true;
        }

        return $this->count(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_preparations preparation
                JOIN dirty_job_runs run ON run.id = preparation.dirty_job_run_id
                WHERE run.user_id = ?
            SQL,
            [$userId]
        ) > 0;
    }

    private function hasEvent(int $userId, string $actionType): bool
    {
        return $this->count(
            'SELECT COUNT(*) FROM tutorial_objective_events WHERE user_id = ? AND action_type = ?',
            [$userId, $actionType]
        ) > 0;
    }

    private function count(string $sql, array $parameters): int
    {
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }
}
