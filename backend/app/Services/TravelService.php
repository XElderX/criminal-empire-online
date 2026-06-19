<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class TravelService
{
    public function travel(array $user, ?string $regionSlug, ?string $locationSlug): array
    {
        $map = new WorldMapService();
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            if ($locationSlug !== null && $locationSlug !== '') {
                $location = $map->findLocation($locationSlug);
                if (!$location || (int) $location['is_active'] !== 1) {
                    throw new RuntimeException('Destination location is not available.');
                }

                $region = $map->findRegionById((int) $location['region_id']);
            } else {
                $region = $map->findRegion((string) $regionSlug);
                if (!$region || (int) $region['is_active'] !== 1) {
                    throw new RuntimeException('Destination region is not available.');
                }

                $locations = $map->locationsForRegion((int) $region['id']);
                $location = $locations[0] ?? null;
            }

            if (!$location) {
                throw new RuntimeException('Destination location is not available.');
            }

            $freshUser = $this->lockUser((int) $user['id']);
            $preview = $map->travelPreview($region, $location);
            $cashCost = (int) $preview['cash_cost'];
            $energyCost = (int) $preview['energy_cost'];

            if ((int) $freshUser['cash'] < $cashCost) {
                throw new RuntimeException('Not enough cash to travel there.');
            }

            if ((int) $freshUser['energy'] < $energyCost) {
                throw new RuntimeException('Not enough energy to travel there.');
            }

            $map->ensureLocationState((int) $freshUser['id']);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE users
                    SET cash = cash - ?, energy = energy - ?, updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$cashCost, $energyCost, $freshUser['id']]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE user_location_state
                    SET
                        current_region_id = ?,
                        current_location_id = ?,
                        last_travel_at = NOW(),
                        travel_cooldown_until = DATE_ADD(NOW(), INTERVAL 15 SECOND),
                        updated_at = NOW()
                    WHERE user_id = ?
                SQL
            )->execute([$region['id'], $location['id'], $freshUser['id']]);

            AuditService::log((int) $freshUser['id'], 'world_map.travel', [
                'region_slug' => $region['slug'],
                'location_slug' => $location['slug'],
                'cash_cost' => $cashCost,
                'energy_cost' => $energyCost,
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Travel complete.',
                'currentLocation' => $map->currentLocation((int) $freshUser['id']),
                'costs' => [
                    'cash' => $cashCost,
                    'energy' => $energyCost,
                ],
                'warnings' => $preview['warnings'],
                'updatedPlayerStats' => [
                    'cash' => max(0, (int) $freshUser['cash'] - $cashCost),
                    'energy' => max(0, (int) $freshUser['energy'] - $energyCost),
                ],
                'possibleActions' => $map->activityLinks((int) $location['id']),
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
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
