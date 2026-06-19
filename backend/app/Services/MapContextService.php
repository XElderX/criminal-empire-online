<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class MapContextService
{
    public function resolve(array $user, ?string $regionSlug = null, ?string $locationSlug = null): array
    {
        $map = new WorldMapService();

        if ($locationSlug !== null && $locationSlug !== '') {
            $location = $map->findLocation($locationSlug);
            if (!$location || (int) $location['is_active'] !== 1) {
                throw new RuntimeException('World location not found.');
            }

            $region = $map->findRegionById((int) $location['region_id']);
        } elseif ($regionSlug !== null && $regionSlug !== '') {
            $region = $map->findRegion($regionSlug);
            if (!$region || (int) $region['is_active'] !== 1) {
                throw new RuntimeException('World region not found.');
            }

            $locations = $map->locationsForRegion((int) $region['id']);
            $location = $locations[0] ?? null;
        } else {
            $current = $map->currentLocation((int) $user['id']);
            $location = $map->findLocation((string) $current['location_slug']);
            $region = $map->findRegion((string) $current['region_slug']);
        }

        if (!$region || !$location) {
            throw new RuntimeException('Location context could not be resolved.');
        }

        $territory = $map->territoryForLocation($location);
        $risk = (new MapRiskService())->summarize($location, $territory);
        $current = $map->currentLocation((int) $user['id']);
        $isHere = $current['region_slug'] === $region['slug']
            && $current['location_slug'] === $location['slug'];

        return [
            'region' => $map->hydrateRegion($region),
            'location' => $map->hydrateLocation($location),
            'territory' => $territory ? $map->formatTerritory($territory) : null,
            'riskSummary' => $risk,
            'currentLocation' => $current,
            'playerIsHere' => $isHere,
            'localModifiers' => (new LocationRiskModifierService())->forLocation($location, $territory),
        ];
    }

    public function fromRequest(array $user): ?array
    {
        $region = isset($_GET['region']) ? (string) $_GET['region'] : null;
        $location = isset($_GET['location']) ? (string) $_GET['location'] : null;

        if (($region === null || $region === '') && ($location === null || $location === '')) {
            return null;
        }

        return $this->resolve($user, $region, $location);
    }
}
