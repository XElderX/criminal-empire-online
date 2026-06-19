<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\DirtyJobService;
use Throwable;

final class DirtyJobController
{
    public function index(array $params, array $context): void
    {
        Response::json([
            'data' => (new DirtyJobService())->opportunities($context['user'], [
                'region' => $_GET['region'] ?? null,
                'location' => $_GET['location'] ?? null,
            ]),
        ]);
    }

    public function active(array $params, array $context): void
    {
        Response::json([
            'data' => (new DirtyJobService())->activeRuns($context['user']),
        ]);
    }

    public function history(array $params, array $context): void
    {
        Response::json([
            'data' => (new DirtyJobService())->history($context['user']),
        ]);
    }

    public function show(array $params, array $context): void
    {
        try {
            Response::json(
                (new DirtyJobService())->detail(
                    $context['user'],
                    (int) $params['id']
                )
            );
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function accept(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new DirtyJobService())->accept(
                $context['user'],
                (int) $params['id'],
                (string) ($data['idempotency_key'] ?? '')
            );

            Response::json($result, 201);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function prepare(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new DirtyJobService())->prepare(
                $context['user'],
                (int) $params['id'],
                (string) ($data['action_code'] ?? '')
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function assignCrew(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new DirtyJobService())->assignCrew(
                $context['user'],
                (int) $params['id'],
                is_array($data['assignments'] ?? null)
                    ? $data['assignments']
                    : []
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function execute(array $params, array $context): void
    {
        try {
            $result = (new DirtyJobService())->startExecution(
                $context['user'],
                (int) $params['id']
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function decide(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new DirtyJobService())->submitDecision(
                $context['user'],
                (int) $params['id'],
                (string) ($data['decision_code'] ?? '')
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function resolve(array $params, array $context): void
    {
        try {
            $result = (new DirtyJobService())->resolve(
                $context['user'],
                (int) $params['id']
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
