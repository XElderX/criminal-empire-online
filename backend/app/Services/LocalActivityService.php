<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class LocalActivityService
{
    public function forRegion(array $user, string $regionSlug): array
    {
        $map = new WorldMapService();
        $region = $map->findRegion($regionSlug);

        if (!$region || (int) $region['is_active'] !== 1) {
            throw new RuntimeException('World region not found.');
        }

        $locations = $map->locationsForRegion((int) $region['id']);
        $groups = [];
        foreach ($locations as $location) {
            $activities = $this->forLocation($user, (string) $location['slug']);
            foreach ($activities['activityGroups'] as $group) {
                $key = $group['key'];
                if (!isset($groups[$key])) {
                    $groups[$key] = $group;
                    continue;
                }
                $groups[$key]['availableCount'] += $group['availableCount'];
                $groups[$key]['lockedCount'] += $group['lockedCount'];
                $groups[$key]['preview'] = array_slice(array_merge($groups[$key]['preview'], $group['preview']), 0, 6);
            }
        }

        return [
            'region' => $map->hydrateRegion($region),
            'currentLocation' => $map->currentLocation((int) $user['id']),
            'activityGroups' => array_values($groups),
        ];
    }

    public function forLocation(array $user, string $locationSlug): array
    {
        $context = (new MapContextService())->resolve($user, null, $locationSlug);
        $quick = $this->quickCrimePreview($user, $context);
        $dirty = $this->dirtyJobPreview($user, $context);
        $local = $this->localOpportunities((int) $user['id'], (int) $context['location']['id']);
        $recruitment = $this->recruitmentPreview($context);
        $business = $this->businessPreview($context);
        $territory = $context['territory'];
        $heatWarnings = $context['riskSummary']['score'] >= 45 ? [[
            'title' => $context['riskSummary']['label'],
            'description' => 'Local heat, police pressure, and danger modify actions here.',
            'riskSummary' => $context['riskSummary'],
        ]] : [];

        return [
            'location' => $context['location'],
            'region' => $context['region'],
            'currentLocation' => $context['currentLocation'],
            'playerIsHere' => $context['playerIsHere'],
            'activityGroups' => array_values(array_filter([
                $this->group('quick_crimes', 'Quick Crimes Nearby', $quick['available'], $quick['locked'], $quick['preview'], 'crimes?tab=quick_crimes'),
                $this->group('dirty_jobs', 'Dirty Jobs Nearby', $dirty['available'], $dirty['locked'], $dirty['preview'], 'dirty jobs'),
                $this->group('crime_leads', 'Crime Leads / Rumors', count($local), 0, $local, 'crimes?tab=explore_leads'),
                $this->group('recruitment', 'Recruitment Nearby', count($recruitment), 0, $recruitment, 'recruitment'),
                $this->group('businesses', 'Businesses Nearby', count($business), 0, $business, 'territories'),
                $territory ? $this->group('territory', 'Territory Control', 1, 0, [$territory], 'territories') : null,
                $this->group('heat_police', 'Heat & Police', count($heatWarnings), 0, $heatWarnings, 'heat'),
            ])),
            'quickCrimesPreview' => $quick['preview'],
            'dirtyJobsPreview' => $dirty['preview'],
            'crimeLeadsPreview' => $local,
            'recruitmentPreview' => $recruitment,
            'businessesPreview' => $business,
            'territorySummary' => $territory,
            'heatSummary' => $context['riskSummary'],
            'actions' => $this->actionsForContext($context),
            'localModifiers' => $context['localModifiers'],
        ];
    }

    private function quickCrimePreview(array $user, array $context): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT q.id, q.code, q.title, q.category, q.min_level, q.energy_cost,
                       q.reward_min, q.reward_max, q.heat_min, q.heat_max,
                       rule.requires_current_location, rule.reward_multiplier, rule.heat_multiplier,
                       rule.police_risk_multiplier, rule.danger_multiplier
                FROM quick_crime_location_rules rule
                JOIN quick_crime_templates q ON q.id = rule.quick_crime_template_id
                WHERE rule.is_allowed = 1
                  AND q.active = 1
                  AND (rule.world_location_id = ? OR (rule.world_location_id IS NULL AND rule.world_region_id = ?))
                ORDER BY rule.sort_order, q.tier, q.id
            SQL
        );
        $statement->execute([$context['location']['id'], $context['region']['id']]);
        $rows = $statement->fetchAll();
        $available = 0;
        $locked = 0;
        $preview = [];

        foreach ($rows as $row) {
            $lockedReasons = [];
            if ((int) $user['level'] < (int) $row['min_level']) {
                $lockedReasons[] = 'Requires level ' . (int) $row['min_level'];
            }
            if ((int) $row['requires_current_location'] === 1 && !$context['playerIsHere']) {
                $lockedReasons[] = 'Travel here to start.';
            }
            if ($lockedReasons === []) {
                $available++;
            } else {
                $locked++;
            }
            $preview[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'category' => $row['category'],
                'available' => $lockedReasons === [],
                'lockedReasons' => $lockedReasons,
                'rewardRange' => [
                    (int) round((int) $row['reward_min'] * (float) $row['reward_multiplier']),
                    (int) round((int) $row['reward_max'] * (float) $row['reward_multiplier']),
                ],
                'location' => $context['region']['name'] . ' / ' . $context['location']['name'],
                'localModifiers' => (new LocationRiskModifierService())->forLocation($context['location'], $context['territory'], $row),
            ];
        }

        return ['available' => $available, 'locked' => $locked, 'preview' => array_slice($preview, 0, 6)];
    }

    private function dirtyJobPreview(array $user, array $context): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT template.id AS template_id, template.title, template.category, template.min_level,
                       template.reward_min, template.reward_max, rule.reward_multiplier, rule.requires_current_location
                FROM dirty_job_location_rules rule
                JOIN dirty_job_templates template ON template.id = rule.dirty_job_template_id
                WHERE template.active = 1
                  AND (rule.world_location_id = ? OR (rule.world_location_id IS NULL AND rule.world_region_id = ?))
                ORDER BY rule.sort_order, template.tier, template.id
            SQL
        );
        $statement->execute([$context['location']['id'], $context['region']['id']]);
        $rows = $statement->fetchAll();
        $available = 0;
        $locked = 0;
        $preview = [];

        foreach ($rows as $row) {
            $lockedReasons = [];
            if ((int) $user['level'] < (int) $row['min_level']) {
                $lockedReasons[] = 'Requires level ' . (int) $row['min_level'];
            }
            if ((int) $row['requires_current_location'] === 1 && !$context['playerIsHere']) {
                $lockedReasons[] = 'Travel here to accept.';
            }
            $lockedReasons === [] ? $available++ : $locked++;
            $preview[] = [
                'template_id' => (int) $row['template_id'],
                'title' => $row['title'],
                'category' => $row['category'],
                'available' => $lockedReasons === [],
                'lockedReasons' => $lockedReasons,
                'rewardRange' => [
                    (int) round((int) $row['reward_min'] * (float) $row['reward_multiplier']),
                    (int) round((int) $row['reward_max'] * (float) $row['reward_multiplier']),
                ],
                'location' => $context['region']['name'] . ' / ' . $context['location']['name'],
            ];
        }

        return ['available' => $available, 'locked' => $locked, 'preview' => array_slice($preview, 0, 6)];
    }

    private function localOpportunities(int $userId, int $locationId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT id, opportunity_type, title, description, status, expires_at, discovered_at
                FROM local_opportunities
                WHERE user_id = ?
                  AND world_location_id = ?
                  AND status = 'available'
                  AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY id DESC
                LIMIT 5
            SQL
        );
        $statement->execute([$userId, $locationId]);
        return $statement->fetchAll();
    }

    private function recruitmentPreview(array $context): array
    {
        $type = $context['location']['location_type'];
        $goodTypes = ['bar', 'nightlife', 'garage', 'docks', 'industrial', 'motel', 'old_town', 'warehouse', 'point_of_interest'];
        if (!in_array($type, $goodTypes, true)) {
            return [];
        }
        return [[
            'title' => 'Local recruitment lead',
            'description' => 'This hotspot can influence the flavor of nearby recruits.',
            'recommended_roles' => $this->roleHints($context['location']['slug']),
        ]];
    }

    private function businessPreview(array $context): array
    {
        $businessSlugs = ['shopping-plaza', 'market-district', 'nightlife-district', 'warehouses', 'gas-station', 'suburban-garage', 'boardwalk', 'container-yard', 'marina'];
        if (!in_array($context['location']['slug'], $businessSlugs, true)) {
            return [];
        }
        return [[
            'title' => 'Business scouting possible',
            'description' => 'This location is suitable for future business, protection, or territory economy hooks.',
        ]];
    }

    private function roleHints(string $slug): array
    {
        return match ($slug) {
            'workers-bar', 'scrapyard' => ['driver', 'enforcer', 'mechanic'],
            'underground-club', 'nightlife-district' => ['thief', 'scout', 'negotiator'],
            'basement-bars' => ['fixer', 'negotiator', 'veteran'],
            'suburban-garage' => ['driver', 'mechanic'],
            'container-yard', 'dock-office', 'smuggler-pier' => ['smuggler', 'warehouse hand'],
            default => ['recruit'],
        };
    }

    private function group(string $key, string $title, int $available, int $locked, array $preview, string $route): ?array
    {
        if ($available === 0 && $locked === 0 && $preview === []) {
            return null;
        }

        return [
            'key' => $key,
            'title' => $title,
            'availableCount' => $available,
            'lockedCount' => $locked,
            'preview' => $preview,
            'route_hint' => $route,
        ];
    }

    private function actionsForContext(array $context): array
    {
        $query = '?region=' . $context['region']['slug'] . '&location=' . $context['location']['slug'];
        return [
            ['label' => 'View Nearby Quick Crimes', 'route_hint' => 'crimes?tab=quick_crimes' . $query],
            ['label' => 'View Local Dirty Jobs', 'route_hint' => 'dirty jobs' . $query],
            ['label' => 'Search Recruits Here', 'route_hint' => 'recruitment' . $query],
            ['label' => 'View Businesses Here', 'route_hint' => 'territories' . $query],
            ['label' => 'Open Heat Warnings', 'route_hint' => 'heat'],
        ];
    }
}
