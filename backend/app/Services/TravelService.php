<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class TravelService
{
    private TravelRiskService $risk;
    private LocalPresenceService $presence;
    private TravelEventService $events;

    public function __construct(
        private readonly RandomSource $random = new SecureRandomSource()
    ) {
        $this->risk = new TravelRiskService();
        $this->presence = new LocalPresenceService();
        $this->events = new TravelEventService($this->random);
    }

    public function travel(array $user, ?string $regionSlug, ?string $locationSlug, ?string $routeType = null): array
    {
        $map = new WorldMapService();
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            [$region, $location] = $this->resolveDestination($map, $regionSlug, $locationSlug);
            $freshUser = $this->lockUser((int) $user['id']);
            $map->ensureLocationState((int) $freshUser['id']);
            $currentBefore = $map->currentLocation((int) $freshUser['id']);
            $isSameLocation = (int) ($currentBefore['region_id'] ?? 0) === (int) $region['id']
                && (int) ($currentBefore['location_id'] ?? 0) === (int) $location['id'];

            if ($isSameLocation) {
                $presence = $this->presence->updatePresenceAfterArrival(
                    (int) $freshUser['id'],
                    (int) $region['id'],
                    (int) $location['id']
                );
                $pdo->commit();

                return $this->response(
                    $freshUser,
                    $region,
                    $location,
                    $currentBefore,
                    $map->currentLocation((int) $freshUser['id']),
                    'stationary',
                    'You are already at ' . $location['name'] . '.',
                    ['cash' => 0, 'energy' => 0],
                    'same_location',
                    null,
                    [],
                    $presence,
                    null
                );
            }

            $routeType = $this->risk->normalizeRouteType($routeType, $region);
            $preview = $this->risk->preview($region, $location, $freshUser, $routeType);
            $cashCost = (int) $preview['cash_cost'];
            $energyCost = (int) $preview['energy_cost'];

            if ((int) $freshUser['cash'] < $cashCost) {
                throw new RuntimeException('Not enough cash to travel there.');
            }

            if ((int) $freshUser['energy'] < $energyCost) {
                throw new RuntimeException('Not enough energy to travel there.');
            }

            $event = $this->events->maybeCreate($freshUser, $region, $location, $preview);
            $heatDelta = (int) ($event['heat_delta'] ?? 0);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET cash = cash - ?, energy = energy - ?, heat = GREATEST(0, heat + ?), updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$cashCost, $energyCost, $heatDelta, $freshUser['id']]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE user_location_state
                    SET
                        last_region_id = current_region_id,
                        last_location_id = current_location_id,
                        current_region_id = ?,
                        current_location_id = ?,
                        last_travel_at = NOW(),
                        arrived_at = NOW(),
                        travel_route_type = ?,
                        travel_status = ?,
                        travel_cooldown_until = DATE_ADD(NOW(), INTERVAL 15 SECOND),
                        updated_at = NOW()
                    WHERE user_id = ?
                SQL
            )->execute([
                $region['id'],
                $location['id'],
                $routeType,
                $event && $event['type'] === 'police_checkpoint' ? 'stopped_by_police' : 'arrived',
                $freshUser['id'],
            ]);

            $presence = $this->presence->updatePresenceAfterArrival(
                (int) $freshUser['id'],
                (int) $region['id'],
                (int) $location['id']
            );

            $historyEntry = $this->recordTravelLog(
                (int) $freshUser['id'],
                $currentBefore,
                $region,
                $location,
                $routeType,
                $event && $event['type'] === 'police_checkpoint' ? 'stopped_by_police' : 'arrived',
                $cashCost,
                $energyCost,
                $heatDelta,
                $event
            );

            AuditService::log((int) $freshUser['id'], 'world_map.travel', [
                'from_region_slug' => $currentBefore['region_slug'] ?? null,
                'from_location_slug' => $currentBefore['location_slug'] ?? null,
                'region_slug' => $region['slug'],
                'location_slug' => $location['slug'],
                'route_type' => $routeType,
                'cash_cost' => $cashCost,
                'energy_cost' => $energyCost,
                'event_type' => $event['type'] ?? null,
                'heat_delta' => $heatDelta,
            ]);

            $pdo->commit();

            $updatedCurrent = $map->currentLocation((int) $freshUser['id']);
            return $this->response(
                $freshUser,
                $region,
                $location,
                $currentBefore,
                $updatedCurrent,
                'arrived',
                'You arrived at ' . $region['name'] . ' / ' . $location['name'] . '.',
                ['cash' => $cashCost, 'energy' => $energyCost],
                $routeType,
                $event,
                $preview['warnings'],
                $presence,
                $historyEntry
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function travelAndExplore(array $user, ?string $regionSlug, ?string $locationSlug, ?string $routeType = null): array
    {
        $travel = $this->travel($user, $regionSlug, $locationSlug, $routeType);
        if (!in_array($travel['travelResult'] ?? '', ['arrived', 'stationary'], true)) {
            throw new RuntimeException('Travel did not finish, so exploration was not started.');
        }

        $exploration = (new HotspotExplorationService())->explore($user, (string) $travel['toLocation']['location_slug']);

        return [
            'success' => true,
            'message' => $travel['message'] . ' ' . $exploration['message'],
            'travel' => $travel,
            'exploration' => $exploration,
            'currentLocation' => $travel['currentLocation'],
            'updatedPlayerStats' => $travel['updatedPlayerStats'],
            'outcome_payload' => (new OutcomePayloadService())->action(
                'World Map',
                'Travel & Explore Report',
                $travel['message'] . ' ' . $exploration['message'],
                'travel',
                'high',
                [
                    'cash' => -1 * (int) ($travel['costs']['cash'] ?? 0),
                    'energy' => -1 * (int) ($travel['costs']['energy'] ?? 0),
                    'heat' => (int) ($travel['heatChange'] ?? 0),
                ],
                [[
                    'label' => 'Use the lead',
                    'description' => 'Check nearby local actions and map opportunities.'
                ]]
            ),
        ];
    }

    public function history(int $userId, int $limit = 20): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    log.*,
                    from_region.name AS from_region_name,
                    from_location.name AS from_location_name,
                    to_region.name AS to_region_name,
                    to_location.name AS to_location_name
                FROM user_travel_logs log
                LEFT JOIN world_regions from_region ON from_region.id = log.from_region_id
                LEFT JOIN world_locations from_location ON from_location.id = log.from_location_id
                JOIN world_regions to_region ON to_region.id = log.to_region_id
                LEFT JOIN world_locations to_location ON to_location.id = log.to_location_id
                WHERE log.user_id = ?
                ORDER BY log.id DESC
                LIMIT ?
            SQL
        );
        $statement->bindValue(1, $userId, \PDO::PARAM_INT);
        $statement->bindValue(2, max(1, min(100, $limit)), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'from_region_name' => $row['from_region_name'],
            'from_location_name' => $row['from_location_name'],
            'to_region_name' => $row['to_region_name'],
            'to_location_name' => $row['to_location_name'],
            'route_type' => $row['route_type'],
            'status' => $row['status'],
            'cash_cost' => (int) $row['cash_cost'],
            'energy_cost' => (int) $row['energy_cost'],
            'heat_delta' => (int) $row['heat_delta'],
            'event_type' => $row['event_type'],
            'event_payload' => json_decode((string) ($row['event_payload'] ?? ''), true) ?: null,
            'created_at' => $row['created_at'],
        ], $statement->fetchAll());
    }

    private function resolveDestination(WorldMapService $map, ?string $regionSlug, ?string $locationSlug): array
    {
        if ($locationSlug !== null && $locationSlug !== '') {
            $location = $map->findLocation($locationSlug);
            if (!$location || (int) $location['is_active'] !== 1) {
                throw new RuntimeException('Destination location is not available.');
            }

            return [$map->findRegionById((int) $location['region_id']), $location];
        }

        $region = $map->findRegion((string) $regionSlug);
        if (!$region || (int) $region['is_active'] !== 1) {
            throw new RuntimeException('Destination region is not available.');
        }

        $locations = $map->locationsForRegion((int) $region['id']);
        $location = $locations[0] ?? null;
        if (!$location) {
            throw new RuntimeException('Destination location is not available.');
        }

        return [$region, $location];
    }

    private function response(
        array $freshUser,
        array $region,
        array $location,
        array $from,
        array $current,
        string $travelResult,
        string $message,
        array $costs,
        string $routeType,
        ?array $event,
        array $warnings,
        array $presence,
        ?array $historyEntry
    ): array {
        $activities = (new LocalActivityService())->forLocation($freshUser, (string) $location['slug']);
        $unlocked = $this->unlockedActions($activities);
        $heatDelta = (int) ($event['heat_delta'] ?? 0);

        return [
            'success' => true,
            'travelResult' => $travelResult,
            'message' => $message,
            'fromLocation' => [
                'region_slug' => $from['region_slug'] ?? null,
                'region_name' => $from['region_name'] ?? null,
                'location_slug' => $from['location_slug'] ?? null,
                'location_name' => $from['location_name'] ?? null,
            ],
            'toLocation' => [
                'region_slug' => $region['slug'],
                'region_name' => $region['name'],
                'location_slug' => $location['slug'],
                'location_name' => $location['name'],
            ],
            'routeType' => $routeType,
            'costs' => $costs,
            'currentLocation' => $current,
            'presence' => $presence,
            'event' => $event,
            'warnings' => $warnings,
            'unlockedActions' => $unlocked,
            'localActivitySummary' => $activities['localActivitySummary'] ?? [],
            'heatChange' => $heatDelta,
            'discoveredOpportunity' => $event['discoveredOpportunity'] ?? null,
            'historyEntry' => $historyEntry,
            'updatedPlayerStats' => [
                'cash' => max(0, (int) $freshUser['cash'] - (int) $costs['cash']),
                'energy' => max(0, (int) $freshUser['energy'] - (int) $costs['energy']),
                'heat' => max(0, (int) ($freshUser['heat'] ?? 0) + $heatDelta),
            ],
            'possibleActions' => (new WorldMapService())->activityLinks((int) $location['id']),
            'outcome_payload' => (new OutcomePayloadService())->travel([
                'message' => $message,
                'event' => $event,
                'costs' => $costs,
                'heatChange' => $heatDelta,
            ]),
        ];
    }

    private function unlockedActions(array $activities): array
    {
        $unlocked = [];
        foreach ($activities['activityGroups'] ?? [] as $group) {
            $key = (string) $group['key'];
            $unlocked[$key] = (int) ($group['availableCount'] ?? 0);
        }

        return [
            'quickCrimes' => $unlocked['quick_crimes'] ?? 0,
            'dirtyJobs' => $unlocked['dirty_jobs'] ?? 0,
            'recruitment' => $unlocked['recruitment'] ?? 0,
            'businesses' => $unlocked['businesses'] ?? 0,
            'territory' => $unlocked['territory'] ?? 0,
        ];
    }

    private function recordTravelLog(
        int $userId,
        array $from,
        array $region,
        array $location,
        string $routeType,
        string $status,
        int $cashCost,
        int $energyCost,
        int $heatDelta,
        ?array $event
    ): array {
        $pdo = Database::pdo();
        $pdo->prepare(
            <<<'SQL'
                INSERT INTO user_travel_logs (
                    user_id,
                    from_region_id,
                    from_location_id,
                    to_region_id,
                    to_location_id,
                    route_type,
                    status,
                    cash_cost,
                    energy_cost,
                    heat_delta,
                    event_type,
                    event_payload,
                    discovered_type,
                    discovered_id,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            $userId,
            $from['region_id'] ?? null,
            $from['location_id'] ?? null,
            $region['id'],
            $location['id'],
            $routeType,
            $status,
            $cashCost,
            $energyCost,
            $heatDelta,
            $event['type'] ?? null,
            $event ? json_encode($event, JSON_THROW_ON_ERROR) : null,
            $event['discoveredOpportunity']['type'] ?? null,
            $event['discoveredOpportunity']['id'] ?? null,
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'status' => $status,
            'route_type' => $routeType,
            'event_type' => $event['type'] ?? null,
            'heat_delta' => $heatDelta,
        ];
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
}
