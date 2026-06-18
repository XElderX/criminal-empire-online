<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CrimeOpportunityService;
use App\Services\CrimeService;
use Throwable;

final class CrimeController
{
    public function index(array $params = [], array $context = []): void
    {
        try {
            Response::json((new CrimeOpportunityService())->overview($context['user']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function commit(array $params, array $context): void
    {
        try {
            Response::json((new CrimeService())->commit($context['user'], (int) $params['id']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function logs(array $params, array $context): void
    {
        try {
            Response::json([
                'data' => (new CrimeOpportunityService())->history($context['user'], 50),
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function explore(array $params, array $context): void
    {
        try {
            Response::json(
                (new CrimeOpportunityService())->explore(
                    $context['user'],
                    (string) ($params['code'] ?? '')
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function showOpportunity(array $params, array $context): void
    {
        try {
            Response::json([
                'opportunity' => (new CrimeOpportunityService())->opportunity(
                    (int) $context['user']['id'],
                    (int) $params['id']
                ),
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function investigate(array $params, array $context): void
    {
        try {
            Response::json(
                (new CrimeOpportunityService())->investigate(
                    $context['user'],
                    (int) $params['id']
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function prepare(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json(
                (new CrimeOpportunityService())->prepare(
                    $context['user'],
                    (int) $params['id'],
                    (string) ($payload['code'] ?? '')
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function assignCrew(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json(
                (new CrimeOpportunityService())->assignCrew(
                    $context['user'],
                    (int) $params['id'],
                    is_array($payload['assignments'] ?? null) ? $payload['assignments'] : []
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function assignEquipment(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json(
                (new CrimeOpportunityService())->assignEquipment(
                    $context['user'],
                    (int) $params['id'],
                    is_array($payload['equipment'] ?? null) ? $payload['equipment'] : []
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function start(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json(
                (new CrimeOpportunityService())->start(
                    $context['user'],
                    (int) $params['id'],
                    isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function decide(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json(
                (new CrimeOpportunityService())->decide(
                    $context['user'],
                    (int) $params['id'],
                    (string) ($payload['decision_code'] ?? '')
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function abandon(array $params, array $context): void
    {
        try {
            Response::json(
                (new CrimeOpportunityService())->abandon(
                    $context['user'],
                    (int) $params['id']
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function contacts(array $params, array $context): void
    {
        try {
            Response::json(['data' => (new CrimeOpportunityService())->contacts((int) $context['user']['id'])]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
