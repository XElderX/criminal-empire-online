<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class HotspotExplorationService
{
    private const ENERGY_COST = 3;
    private const COOLDOWN_SECONDS = 600;

    public function explore(array $user, string $locationSlug): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $context = (new MapContextService())->resolve($user, null, $locationSlug);
            $freshUser = $this->lockUser((int) $user['id']);

            if ((int) $context['location']['is_active'] !== 1) {
                throw new RuntimeException('This location is not available.');
            }

            if (!$context['playerIsHere']) {
                throw new RuntimeException('Travel to ' . $context['region']['name'] . ' / ' . $context['location']['name'] . ' before exploring this hotspot.');
            }

            if ((int) $freshUser['energy'] < self::ENERGY_COST) {
                throw new RuntimeException('Not enough energy to explore this hotspot.');
            }

            $cooldown = $this->cooldownRemaining((int) $freshUser['id'], (int) $context['location']['id']);
            if ($cooldown > 0) {
                throw new RuntimeException('This hotspot was explored recently. Try again in ' . $cooldown . ' seconds.');
            }

            $pdo->prepare('UPDATE users SET energy = energy - ?, updated_at = NOW() WHERE id = ?')
                ->execute([self::ENERGY_COST, $freshUser['id']]);

            $result = $this->resultFor($context);
            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO local_opportunities (
                        user_id, world_region_id, world_location_id, opportunity_type, source_type,
                        title, description, status, expires_at, discovered_at, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, 'hotspot_exploration', ?, ?, 'available', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW(), NOW())
                SQL
            )->execute([
                $freshUser['id'],
                $context['region']['id'],
                $context['location']['id'],
                $result['type'],
                $result['title'],
                $result['description'],
            ]);
            $opportunityId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO location_exploration_logs (
                        user_id, world_region_id, world_location_id, result_type, result_id, energy_cost, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                SQL
            )->execute([
                $freshUser['id'],
                $context['region']['id'],
                $context['location']['id'],
                $result['type'],
                $opportunityId,
                self::ENERGY_COST,
            ]);

            (new LocalPresenceService())->markExplored(
                (int) $freshUser['id'],
                (int) $context['region']['id'],
                (int) $context['location']['id']
            );

            $pdo->prepare('UPDATE user_location_state SET last_local_action_at = NOW(), updated_at = NOW() WHERE user_id = ?')
                ->execute([$freshUser['id']]);

            AuditService::log((int) $freshUser['id'], 'world_map.explore_hotspot', [
                'region' => $context['region']['slug'],
                'location' => $context['location']['slug'],
                'result_type' => $result['type'],
            ]);

            $pdo->commit();

            return [
                'message' => 'Hotspot explored.',
                'energy_cost' => self::ENERGY_COST,
                'opportunity' => ['id' => $opportunityId] + $result,
                'activities' => (new LocalActivityService())->forLocation($freshUser, $locationSlug),
            ];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function cooldownRemaining(int $userId, int $locationId): int
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT GREATEST(0, ? - TIMESTAMPDIFF(SECOND, MAX(created_at), NOW())) AS remaining
                FROM location_exploration_logs
                WHERE user_id = ?
                  AND world_location_id = ?
            SQL
        );
        $statement->execute([self::COOLDOWN_SECONDS, $userId, $locationId]);
        return (int) ($statement->fetchColumn() ?: 0);
    }

    private function resultFor(array $context): array
    {
        $slug = $context['location']['slug'];
        return match (true) {
            str_contains($slug, 'bar'), str_contains($slug, 'club') => [
                'type' => 'recruitment_lead',
                'title' => 'A useful name from the room',
                'description' => 'Someone mentions a potential recruit or contact who works this area.',
            ],
            str_contains($slug, 'yard'), str_contains($slug, 'warehouse'), str_contains($slug, 'container') => [
                'type' => 'dirty_job_lead',
                'title' => 'Local work rumor',
                'description' => 'You notice movement that could turn into a nearby dirty job or cargo lead.',
            ],
            str_contains($slug, 'police') => [
                'type' => 'heat_warning',
                'title' => 'Patrol pattern noticed',
                'description' => 'The area is too watched for careless moves, but the warning may help planning.',
            ],
            default => [
                'type' => 'quick_crime_target',
                'title' => 'Small local opening',
                'description' => 'A small fictional opportunity appears nearby. Use the local action panels to decide what to do.',
            ],
        };
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
