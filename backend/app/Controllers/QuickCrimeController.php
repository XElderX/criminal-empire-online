<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\QuickCrimeService;
use Throwable;

final class QuickCrimeController
{
    public function index(array $params = [], array $context = []): void
    {
        try {
            Response::json((new QuickCrimeService())->list($context['user']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function show(array $params, array $context): void
    {
        try {
            Response::json((new QuickCrimeService())->show($context['user'], (int) $params['id']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function prepare(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json(
                (new QuickCrimeService())->prepare(
                    $context['user'],
                    (int) $params['id'],
                    (string) ($payload['code'] ?? '')
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function start(array $params, array $context): void
    {
        try {
            Response::json(
                (new QuickCrimeService())->start(
                    $context['user'],
                    (int) $params['id'],
                    Request::json()
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function run(array $params, array $context): void
    {
        try {
            $runs = (new QuickCrimeService())->history((int) $context['user']['id'], 50);
            foreach ($runs as $run) {
                if ((int) $run['id'] === (int) $params['id']) {
                    Response::json(['run' => $run]);
                    return;
                }
            }

            Response::json(['message' => 'Quick crime run not found.'], 404);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function decide(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json(
                (new QuickCrimeService())->decide(
                    $context['user'],
                    (int) $params['id'],
                    (string) ($payload['decision_code'] ?? '')
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function resolve(array $params, array $context): void
    {
        try {
            Response::json(
                (new QuickCrimeService())->resolve(
                    $context['user'],
                    (int) $params['id']
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function history(array $params, array $context): void
    {
        try {
            Response::json(['data' => (new QuickCrimeService())->history((int) $context['user']['id'], 50)]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function progression(array $params, array $context): void
    {
        try {
            Response::json((new QuickCrimeService())->progression((int) $context['user']['id']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
