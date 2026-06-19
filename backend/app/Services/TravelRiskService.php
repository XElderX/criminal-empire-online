<?php

namespace App\Services;

final class TravelRiskService
{
    public const ROUTE_TYPES = ['cheap', 'fast', 'low_profile', 'back_roads'];

    public function routeOptions(array $region, array $location, array $user = []): array
    {
        $baseEnergy = max(0, (int) $region['travel_cost_energy'] + (int) floor(((int) $location['danger_level']) / 25));
        $baseCash = max(0, (int) $region['travel_cost_cash']);

        $options = [
            $this->option('cheap', 'Cheap route', $baseCash, $baseEnergy + 1, 8, 'Low cash cost, more walking and waiting.'),
            $this->option('fast', 'Fast route', $baseCash + 6, max(1, $baseEnergy - 1), -4, 'Costs more cash, saves energy, fewer delays.'),
            $this->option('low_profile', 'Low-profile route', $baseCash + 10, $baseEnergy, -14, 'Safer when heat is high or police pressure is heavy.'),
        ];

        if (in_array((string) $region['region_type'], ['rural', 'forest', 'outskirts', 'docks', 'shore'], true)) {
            $options[] = $this->option('back_roads', 'Back roads', $baseCash + 3, $baseEnergy + 2, 2, 'Less police visibility, but higher delay and ambush risk.');
        }

        return $options;
    }

    public function preview(array $region, array $location, array $user, string $routeType): array
    {
        $route = $this->routeByType($region, $location, $user, $routeType);
        $risk = (new MapRiskService())->summarize($location, (new WorldMapService())->territoryForLocation($location));
        $carrying = (new CarryingRiskService())->summarize((int) $user['id']);
        $heat = max((int) ($user['heat'] ?? 0), (int) ($user['boss_personal_heat'] ?? 0), (int) ($user['gang_heat'] ?? 0));
        $distance = $this->distanceScore($region, $location);

        $eventChance = 8
            + (int) floor($risk['score'] / 8)
            + (int) floor($heat / 12)
            + (int) floor($distance / 12)
            + (int) floor($carrying['risk_bonus'] / 3)
            + (int) $route['risk_delta'];
        $eventChance = max(3, min(70, $eventChance));

        $policeChance = max(0, min(55, (int) floor($risk['police_pressure'] / 4) + (int) floor($heat / 12) + (int) floor($carrying['risk_bonus'] / 2) + (int) $route['police_delta']));
        $rivalChance = max(0, min(45, (int) floor($risk['danger_level'] / 5) + (int) floor($risk['heat'] / 12) + (int) $route['rival_delta']));

        $warnings = $this->warnings($location, $risk, $heat, $carrying);

        return [
            'route_type' => $route['type'],
            'route_label' => $route['label'],
            'cash_cost' => (int) $route['cash_cost'],
            'energy_cost' => (int) $route['energy_cost'],
            'event_chance' => $eventChance,
            'police_stop_chance' => $policeChance,
            'rival_event_chance' => $rivalChance,
            'travel_risk_score' => min(100, $risk['score'] + (int) floor($heat / 4) + $carrying['risk_bonus']),
            'riskSummary' => $risk,
            'carryingRisk' => $carrying,
            'distance_score' => $distance,
            'warnings' => $warnings,
            'locked_reason' => null,
        ];
    }

    public function routeByType(array $region, array $location, array $user, ?string $routeType): array
    {
        $routeType = $this->normalizeRouteType($routeType, $region);
        foreach ($this->routeOptions($region, $location, $user) as $option) {
            if ($option['type'] === $routeType) {
                return $option;
            }
        }

        return $this->routeOptions($region, $location, $user)[0];
    }

    public function normalizeRouteType(?string $routeType, ?array $region = null): string
    {
        $routeType = trim((string) $routeType);
        if ($routeType === '') {
            return 'cheap';
        }

        if ($routeType === 'back_roads' && $region !== null) {
            return in_array((string) ($region['region_type'] ?? ''), ['rural', 'forest', 'outskirts', 'docks', 'shore'], true)
                ? 'back_roads'
                : 'cheap';
        }

        return in_array($routeType, self::ROUTE_TYPES, true) ? $routeType : 'cheap';
    }

    private function option(string $type, string $label, int $cash, int $energy, int $riskDelta, string $description): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'cash_cost' => max(0, $cash),
            'energy_cost' => max(0, $energy),
            'risk_delta' => $riskDelta,
            'police_delta' => $type === 'low_profile' ? -12 : ($type === 'fast' ? -4 : ($type === 'back_roads' ? -8 : 4)),
            'rival_delta' => $type === 'back_roads' ? 8 : ($type === 'cheap' ? 3 : 0),
            'description' => $description,
        ];
    }

    private function distanceScore(array $region, array $location): int
    {
        return (int) $region['travel_cost_cash'] + ((int) $region['travel_cost_energy'] * 4) + (int) floor(((int) $location['danger_level']) / 3);
    }

    private function warnings(array $location, array $risk, int $heat, array $carrying): array
    {
        $warnings = [];

        if ((int) $location['police_pressure'] >= 70 || $risk['police_pressure'] >= 70) {
            $warnings[] = 'Police presence is high here; checkpoints are more likely.';
        }

        if ((int) $location['danger_level'] >= 55 || $risk['danger_level'] >= 55) {
            $warnings[] = 'This route crosses a dangerous hotspot.';
        }

        if ((int) $location['heat_level'] >= 45 || $risk['heat'] >= 45) {
            $warnings[] = 'Local heat may affect contacts and nearby jobs.';
        }

        if ($heat >= 50) {
            $warnings[] = 'Your current heat makes travel attention more likely.';
        }

        return array_values(array_unique(array_merge($warnings, $carrying['warnings'] ?? [])));
    }
}
