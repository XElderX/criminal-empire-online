<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\JobService;
use Throwable;

final class JobController
{
    public function index(array $params, array $context): void
    {
        Response::json([
            'data' => (new JobService())->listForUser($context['user']),
        ]);
    }

    public function active(array $params, array $context): void
    {
        Response::json([
            'data' => (new JobService())->active($context['user']),
        ]);
    }

    public function start(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new JobService())->start(
                $context['user'],
                (int) $params['id'],
                is_array($data['member_ids'] ?? null)
                    ? $data['member_ids']
                    : [],
                (string) ($data['idempotency_key'] ?? '')
            );

            Response::json($result, 201);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function complete(array $params, array $context): void
    {
        try {
            $result = (new JobService())->complete(
                $context['user'],
                (int) $params['id']
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
