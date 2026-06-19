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
        $shops = (new MapShopService())->countsForLocation((int) $user['id'], (int) $context['location']['id']);
        $recruitment = $this->recruitmentPreview($context);
        $business = $this->businessPreview($context);
        $territory = $context['territory'];
        $heatWarnings = $context['riskSummary']['score'] >= 45 ? [[
            'title' => $context['riskSummary']['label'],
            'description' => 'Local heat, police pressure, and danger modify actions here.',
            'riskSummary' => $context['riskSummary'],
        ]] : [];
        $presence = (new LocalPresenceService())->presenceFor(
            (int) $user['id'],
            $context['region'],
            $context['location']
        );
        $travelPurpose = $this->travelPurpose($context, $quick, $dirty, $recruitment, $business, $local, $shops);
        $activityGroups = array_values(array_filter([
            $this->group('quick_crimes', 'Quick Crimes Nearby', $quick['available'], $quick['locked'], $quick['preview'], 'crimes?tab=quick_crimes', $context['playerIsHere']),
            $this->group('dirty_jobs', 'Dirty Jobs Nearby', $dirty['available'], $dirty['locked'], $dirty['preview'], 'dirty jobs', $context['playerIsHere']),
            $this->group('crime_leads', 'Crime Leads / Rumors', count($local), 0, $local, 'crimes?tab=explore_leads', true),
            $this->group('shops', 'Shops Nearby', $shops['available'], $shops['locked'], $shops['shops'], 'shops', $context['playerIsHere']),
            $this->group('recruitment', 'Recruitment Nearby', $context['playerIsHere'] ? count($recruitment) : 0, $context['playerIsHere'] ? 0 : count($recruitment), $recruitment, 'recruitment', $context['playerIsHere']),
            $this->group('businesses', 'Businesses Nearby', $context['playerIsHere'] ? count($business) : 0, $context['playerIsHere'] ? 0 : count($business), $business, 'territories', $context['playerIsHere']),
            $territory ? $this->group('territory', 'Territory Control', 1, 0, [$territory], 'territories', true) : null,
            $this->group('heat_police', 'Heat & Police', count($heatWarnings), 0, $heatWarnings, 'heat', true),
        ]));

        return [
            'location' => $context['location'],
            'region' => $context['region'],
            'currentLocation' => $context['currentLocation'],
            'playerIsHere' => $context['playerIsHere'],
            'presence' => $presence,
            'travelPurpose' => $travelPurpose,
            'remoteActions' => $travelPurpose['remote'],
            'localUnlocks' => $travelPurpose['unlocks'],
            'activityGroups' => $activityGroups,
            'localActivitySummary' => [
                'available_here' => array_sum(array_map(static fn (array $group): int => (int) $group['availableCount'], $activityGroups)),
                'travel_required' => array_sum(array_map(static fn (array $group): int => (int) $group['lockedCount'], $activityGroups)),
                'quick_crimes_available' => $quick['available'],
                'dirty_jobs_available' => $dirty['available'],
                'known_local_leads' => count($local),
                'shops_nearby' => count($shops['shops']),
                'shops_available_here' => $shops['available'],
                'player_is_here' => $context['playerIsHere'],
            ],
            'quickCrimesPreview' => $quick['preview'],
            'dirtyJobsPreview' => $dirty['preview'],
            'crimeLeadsPreview' => $local,
            'recruitmentPreview' => $recruitment,
            'businessesPreview' => $business,
            'shopsPreview' => $shops['shops'],
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
                'requiresCurrentLocation' => (bool) $row['requires_current_location'],
                'localPresenceStatus' => $context['playerIsHere'] ? 'available_here' : ((int) $row['requires_current_location'] === 1 ? 'travel_required' : 'remote_available'),
                'travelHint' => $context['playerIsHere'] ? null : 'Travel to ' . $context['region']['name'] . ' / ' . $context['location']['name'] . ' to start this.',
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
                'requiresCurrentLocation' => (bool) $row['requires_current_location'],
                'localPresenceStatus' => $context['playerIsHere'] ? 'available_here' : ((int) $row['requires_current_location'] === 1 ? 'travel_required' : 'remote_available'),
                'travelHint' => $context['playerIsHere'] ? null : 'Travel to ' . $context['region']['name'] . ' / ' . $context['location']['name'] . ' to start this.',
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


    private function travelPurpose(array $context, array $quick, array $dirty, array $recruitment, array $business, array $local, array $shops): array
    {
        $items = [];
        if ($quick['available'] + $quick['locked'] > 0) {
            $items[] = $quick['available'] + $quick['locked'] . ' local quick crime(s)';
        }
        if ($dirty['available'] + $dirty['locked'] > 0) {
            $items[] = $dirty['available'] + $dirty['locked'] . ' dirty job lead(s)';
        }
        if ($recruitment !== []) {
            $items[] = 'nearby recruitment flavor';
        }
        if ($business !== []) {
            $items[] = 'business scouting';
        }
        if ($local !== []) {
            $items[] = count($local) . ' discovered local lead(s)';
        }
        if (($shops['available'] + $shops['locked']) > 0) {
            $items[] = ($shops['available'] + $shops['locked']) . ' shop/dealer location(s) for buying or selling gear';
        }
        if ($context['territory']) {
            $items[] = 'territory scouting context';
        }

        return [
            'headline' => $context['playerIsHere'] ? 'You are here. Local actions are unlocked.' : 'Travel here to unlock local actions.',
            'unlocks' => $items,
            'remote' => $this->remoteActions($context),
            'warnings' => array_values(array_filter([
                ((int) $context['riskSummary']['police_pressure'] >= 60) ? 'High police pressure affects travel and local action risk.' : null,
                ((int) $context['riskSummary']['danger_level'] >= 60) ? 'Dangerous hotspot: rival or street trouble is more likely.' : null,
            ])),
            'remote_view_allowed' => true,
            'local_presence_required' => !$context['playerIsHere'],
        ];
    }

    private function remoteActions(array $context): array
    {
        return [
            'View territory summary',
            'Inspect known opportunities',
            'Check police and danger risk',
            'Plan travel route',
        ];
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

    private function group(string $key, string $title, int $available, int $locked, array $preview, string $route, bool $localPresenceSatisfied = true): ?array
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
            'localPresenceSatisfied' => $localPresenceSatisfied,
            'availabilityLabel' => $localPresenceSatisfied ? 'Available here' : 'Requires local presence',
        ];
    }

    private function actionsForContext(array $context): array
    {
        $locationQuery = 'region=' . $context['region']['slug'] . '&location=' . $context['location']['slug'];
        return [
            ['label' => 'View Nearby Quick Crimes', 'route_hint' => 'crimes?tab=quick_crimes&' . $locationQuery],
            ['label' => 'View Local Dirty Jobs', 'route_hint' => 'dirty jobs?' . $locationQuery],
            ['label' => 'Search Recruits Here', 'route_hint' => 'recruitment?' . $locationQuery],
            ['label' => 'View Businesses Here', 'route_hint' => 'territories?' . $locationQuery],
            ['label' => 'Open Shops Nearby', 'route_hint' => 'shops?' . $locationQuery],
            ['label' => 'Open Heat Warnings', 'route_hint' => 'heat'],
        ];
    }
}
