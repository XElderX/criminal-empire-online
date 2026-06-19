<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\AdminMiddleware;
use App\Services\HotspotExplorationService;
use App\Services\LocalActivityService;
use App\Services\TravelService;
use App\Services\WorldMapService;
use Throwable;

final class WorldMapController
{
    public function index(array $params = [], array $context = []): void
    {
        try {
            Response::json((new WorldMapService())->overview($context['user']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function regions(array $params = [], array $context = []): void
    {
        try {
            Response::json((new WorldMapService())->regions());
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function region(array $params, array $context): void
    {
        try {
            Response::json((new WorldMapService())->region($context['user'], (string) $params['slug']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function regionLocations(array $params, array $context): void
    {
        try {
            $response = (new WorldMapService())->region($context['user'], (string) $params['slug']);
            Response::json(['data' => $response['locations']]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function location(array $params, array $context): void
    {
        try {
            Response::json((new WorldMapService())->location($context['user'], (string) $params['slug']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function locationActivities(array $params, array $context): void
    {
        try {
            Response::json((new LocalActivityService())->forLocation($context['user'], (string) $params['slug']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function regionActivities(array $params, array $context): void
    {
        try {
            Response::json((new LocalActivityService())->forRegion($context['user'], (string) $params['slug']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function exploreLocation(array $params, array $context): void
    {
        try {
            Response::json((new HotspotExplorationService())->explore($context['user'], (string) $params['slug']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function currentLocation(array $params, array $context): void
    {
        try {
            Response::json(['currentLocation' => (new WorldMapService())->currentLocation((int) $context['user']['id'])]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function travel(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json((new TravelService())->travel(
                $context['user'],
                isset($payload['region_slug']) ? (string) $payload['region_slug'] : null,
                isset($payload['location_slug']) ? (string) $payload['location_slug'] : null
            ));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function territories(array $params, array $context): void
    {
        try {
            $overview = (new WorldMapService())->overview($context['user']);
            $territories = [];

            foreach ($overview['regions'] as $region) {
                $regionResponse = (new WorldMapService())->region($context['user'], $region['slug']);
                foreach ($regionResponse['locations'] as $location) {
                    if ($location['territory'] !== null) {
                        $territories[] = [
                            'region' => $region['name'],
                            'location' => $location['name'],
                            'territory' => $location['territory'],
                        ];
                    }
                }
            }

            Response::json(['data' => $territories]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function adminOverview(array $params, array $context): void
    {
        try {
            AdminMiddleware::ensure($context['user']);
            Response::json((new WorldMapService())->adminOverview());
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function adminRegions(array $params, array $context): void
    {
        try {
            AdminMiddleware::ensure($context['user']);
            Response::json(['data' => (new WorldMapService())->adminOverview()['regions']]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function adminLocations(array $params, array $context): void
    {
        try {
            AdminMiddleware::ensure($context['user']);
            Response::json(['data' => (new WorldMapService())->adminOverview()['locations']]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
