<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class LocalPresenceService
{
    public function current(int $userId): array
    {
        return (new WorldMapService())->currentLocation($userId);
    }

    public function isAt(int $userId, int $regionId, ?int $locationId): bool
    {
        $current = $this->current($userId);

        if ((int) ($current['region_id'] ?? 0) !== $regionId) {
            return false;
        }

        if ($locationId === null) {
            return true;
        }

        return (int) ($current['location_id'] ?? 0) === $locationId;
    }

    public function assertAt(int $userId, array $region, ?array $location, string $actionLabel = 'this local action'): void
    {
        $regionId = (int) $region['id'];
        $locationId = $location ? (int) $location['id'] : null;

        if ($this->isAt($userId, $regionId, $locationId)) {
            return;
        }

        $name = $location
            ? $region['name'] . ' / ' . $location['name']
            : $region['name'];

        throw new RuntimeException('Travel to ' . $name . ' before starting ' . $actionLabel . '.');
    }

    public function presenceFor(int $userId, array $region, array $location): array
    {
        $current = $this->current($userId);
        $playerIsHere = (int) ($current['region_id'] ?? 0) === (int) $region['id']
            && (int) ($current['location_id'] ?? 0) === (int) $location['id'];

        return [
            'playerIsHere' => $playerIsHere,
            'status' => $playerIsHere ? 'local' : 'remote_view',
            'label' => $playerIsHere ? 'You are here' : 'Travel here to act',
            'message' => $playerIsHere
                ? 'Local presence active. Location-required actions can be started here.'
                : 'You can inspect this hotspot remotely, but location-required actions need travel first.',
            'currentLocation' => $current,
            'requiresTravel' => !$playerIsHere,
            'travelRequirementMessage' => $playerIsHere
                ? null
                : 'Travel to ' . $region['name'] . ' / ' . $location['name'] . ' before starting local actions.',
        ];
    }

    public function updatePresenceAfterArrival(int $userId, int $regionId, int $locationId): array
    {
        $pdo = Database::pdo();
        $statement = $pdo->prepare(
            <<<'SQL'
                INSERT INTO user_location_presence (
                    user_id,
                    world_region_id,
                    world_location_id,
                    visits_count,
                    last_visited_at,
                    familiarity_score,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, 1, NOW(), 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    visits_count = visits_count + 1,
                    last_visited_at = NOW(),
                    familiarity_score = LEAST(100, familiarity_score + 1),
                    updated_at = NOW()
            SQL
        );
        $statement->execute([$userId, $regionId, $locationId]);

        return $this->presenceRecord($userId, $regionId, $locationId);
    }

    public function markExplored(int $userId, int $regionId, int $locationId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            <<<'SQL'
                INSERT INTO user_location_presence (
                    user_id,
                    world_region_id,
                    world_location_id,
                    visits_count,
                    last_visited_at,
                    last_explored_at,
                    familiarity_score,
                    exploration_cooldown_until,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, 1, NOW(), NOW(), 2, DATE_ADD(NOW(), INTERVAL 600 SECOND), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    last_explored_at = NOW(),
                    exploration_cooldown_until = DATE_ADD(NOW(), INTERVAL 600 SECOND),
                    familiarity_score = LEAST(100, familiarity_score + 2),
                    updated_at = NOW()
            SQL
        )->execute([$userId, $regionId, $locationId]);
    }

    private function presenceRecord(int $userId, int $regionId, int $locationId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM user_location_presence
                WHERE user_id = ?
                  AND world_region_id = ?
                  AND world_location_id = ?
                LIMIT 1
            SQL
        );
        $statement->execute([$userId, $regionId, $locationId]);
        $row = $statement->fetch() ?: [];

        return [
            'visits_count' => (int) ($row['visits_count'] ?? 0),
            'last_visited_at' => $row['last_visited_at'] ?? null,
            'last_explored_at' => $row['last_explored_at'] ?? null,
            'familiarity_score' => (int) ($row['familiarity_score'] ?? 0),
            'exploration_cooldown_until' => $row['exploration_cooldown_until'] ?? null,
        ];
    }
}
