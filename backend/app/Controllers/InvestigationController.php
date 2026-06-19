<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\InvestigationService;
use Throwable;

final class InvestigationController
{
    public function index(array $params, array $context): void
    {
        try {
            Response::json(['data' => (new InvestigationService())->listForUser((int) $context['user']['id'])]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function show(array $params, array $context): void
    {
        try {
            Response::json((new InvestigationService())->detail((int) $context['user']['id'], (int) $params['id']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function respond(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json((new InvestigationService())->respond((int) $context['user']['id'], (int) $params['id'], (string) ($payload['response_code'] ?? '')));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
