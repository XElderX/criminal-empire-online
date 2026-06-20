<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\HeatService;
use Throwable;

final class HeatController
{
    public function index(array $params, array $context): void
    {
        try {
            Response::json((new HeatService())->overview($context['user']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function logs(array $params, array $context): void
    {
        try {
            Response::json((new HeatService())->logs($context['user'], $_GET));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reductionOptions(array $params, array $context): void
    {
        try {
            Response::json((new HeatService())->reductionOptions($context['user']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reduce(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json((new HeatService())->reduce($context['user'], (string) ($payload['code'] ?? ''), $payload));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function layLow(array $params, array $context): void
    {
        try {
            $result = (new HeatService())->layLow($context['user']);
            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function processDaily(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json((new HeatService())->processDaily($context['user'], isset($payload['date']) ? (string) $payload['date'] : null));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
