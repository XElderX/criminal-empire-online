<?php

namespace App\Services;

use App\Core\Database;

final class TravelEventService
{
    public function __construct(
        private readonly RandomSource $random = new SecureRandomSource()
    ) {
    }

    public function maybeCreate(array $user, array $region, array $location, array $riskPreview): ?array
    {
        $roll = $this->random->integer(1, 100);
        if ($roll > (int) $riskPreview['event_chance']) {
            return null;
        }

        $event = $this->selectEvent($user, $region, $location, $riskPreview);
        $discovered = null;
        $heatDelta = (int) ($event['heat_delta'] ?? 0);

        if (in_array($event['event_type'], ['rumor', 'quick_crime_target', 'dirty_job_lead', 'recruitment_lead'], true)) {
            $discovered = $this->createOpportunity((int) $user['id'], $region, $location, $event);
        }

        return [
            'type' => $event['event_type'],
            'title' => $event['title'],
            'description' => $event['description'],
            'severity' => $event['severity'] ?? 'minor',
            'heat_delta' => $heatDelta,
            'createdOpportunity' => $discovered !== null,
            'discoveredOpportunity' => $discovered,
        ];
    }

    private function selectEvent(array $user, array $region, array $location, array $riskPreview): array
    {
        $eventType = 'rumor';
        $heatDelta = 0;
        $severity = 'minor';
        $policeRoll = $this->random->integer(1, 100);
        $rivalRoll = $this->random->integer(1, 100);

        if ($policeRoll <= (int) $riskPreview['police_stop_chance']) {
            $eventType = ((int) $riskPreview['police_stop_chance'] >= 35 || (int) ($user['heat'] ?? 0) >= 60)
                ? 'police_checkpoint'
                : 'patrol_pattern';
            $heatDelta = $eventType === 'police_checkpoint' ? 2 : 0;
            $severity = $eventType === 'police_checkpoint' ? 'warning' : 'minor';
        } elseif ($rivalRoll <= (int) $riskPreview['rival_event_chance']) {
            $eventType = 'rival_presence';
            $severity = 'warning';
        } else {
            $pool = $this->positivePoolForLocation((string) $location['slug']);
            $eventType = $pool[$this->random->integer(0, count($pool) - 1)] ?? 'rumor';
        }

        $template = $this->templateFor($eventType, $location);
        $template['heat_delta'] = $heatDelta;
        $template['severity'] = $severity;

        return $template;
    }

    private function templateFor(string $eventType, array $location): array
    {
        $fallback = match ($eventType) {
            'police_checkpoint' => [
                'event_type' => 'police_checkpoint',
                'title' => 'Checkpoint Delay',
                'description' => 'A patrol checkpoint slows the route. The boss keeps it calm, but attention rises slightly.',
            ],
            'patrol_pattern' => [
                'event_type' => 'patrol_pattern',
                'title' => 'Patrol Pattern Noticed',
                'description' => 'You spot how patrols move near the destination. This could help future planning.',
            ],
            'rival_presence' => [
                'event_type' => 'rival_presence',
                'title' => 'Rival Presence',
                'description' => 'A rival crew is visible nearby. Nothing starts, but this area feels watched.',
            ],
            'quick_crime_target' => [
                'event_type' => 'quick_crime_target',
                'title' => 'Small Local Opening',
                'description' => 'You notice a nearby fictional target suitable for a quick local action.',
            ],
            'dirty_job_lead' => [
                'event_type' => 'dirty_job_lead',
                'title' => 'Local Work Rumor',
                'description' => 'Someone mentions quiet work around this hotspot. A local lead is added.',
            ],
            'recruitment_lead' => [
                'event_type' => 'recruitment_lead',
                'title' => 'Recruitment Lead',
                'description' => 'A name comes up in conversation. This hotspot may be useful for finding crew.',
            ],
            'shortcut' => [
                'event_type' => 'shortcut',
                'title' => 'Shortcut Discovered',
                'description' => 'You learn a quieter way through the area. Future route systems can use this familiarity.',
            ],
            default => [
                'event_type' => 'rumor',
                'title' => 'Overheard Local Rumor',
                'description' => 'A passing comment gives this hotspot more context and adds a small local lead.',
            ],
        };

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT event_type, title, description
                FROM travel_event_templates
                WHERE event_type = ?
                  AND is_active = 1
                  AND (min_danger <= ? OR min_danger IS NULL)
                  AND (min_police_pressure <= ? OR min_police_pressure IS NULL)
                ORDER BY weight DESC, id
                LIMIT 1
            SQL
        );
        $statement->execute([$eventType, (int) $location['danger_level'], (int) $location['police_pressure']]);
        $row = $statement->fetch();

        return $row ?: $fallback;
    }

    private function positivePoolForLocation(string $slug): array
    {
        return match (true) {
            str_contains($slug, 'bar'), str_contains($slug, 'club') => ['rumor', 'recruitment_lead', 'dirty_job_lead'],
            str_contains($slug, 'yard'), str_contains($slug, 'warehouse'), str_contains($slug, 'container') => ['dirty_job_lead', 'quick_crime_target', 'patrol_pattern'],
            str_contains($slug, 'police') => ['patrol_pattern', 'rumor'],
            str_contains($slug, 'garage'), str_contains($slug, 'scrap'), str_contains($slug, 'parking') => ['quick_crime_target', 'dirty_job_lead', 'shortcut'],
            default => ['rumor', 'quick_crime_target', 'shortcut'],
        };
    }

    private function createOpportunity(int $userId, array $region, array $location, array $event): array
    {
        $type = match ($event['event_type']) {
            'quick_crime_target' => 'quick_crime_target',
            'dirty_job_lead' => 'dirty_job_lead',
            'recruitment_lead' => 'recruitment_lead',
            default => 'rumor',
        };

        $pdo = Database::pdo();
        $pdo->prepare(
            <<<'SQL'
                INSERT INTO local_opportunities (
                    user_id, world_region_id, world_location_id, opportunity_type, source_type,
                    title, description, status, expires_at, discovered_at, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 'travel_event', ?, ?, 'available', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW(), NOW())
            SQL
        )->execute([
            $userId,
            $region['id'],
            $location['id'],
            $type,
            $event['title'],
            $event['description'],
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'type' => $type,
            'title' => $event['title'],
            'description' => $event['description'],
        ];
    }
}
