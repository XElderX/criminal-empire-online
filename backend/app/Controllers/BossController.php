<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\BossCharacterService;
use App\Services\SuccessionService;
use Throwable;

final class BossController
{
    public function show(array $params, array $context): void
    {
        try {
            Response::json(['boss' => (new BossCharacterService())->profile($context['user'])]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function history(array $params, array $context): void
    {
        try {
            Response::json(['data' => (new BossCharacterService())->history((int) $context['user']['id'])]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function succession(array $params, array $context): void
    {
        try {
            Response::json([
                'current_boss' => (new BossCharacterService())->profile($context['user']),
                'next_candidate' => (new SuccessionService())->bestCandidate((int) $context['user']['id']),
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function rename(array $params, array $context): void
    {
        try {
            $payload = Request::json();

            Response::json([
                'boss' => (new BossCharacterService())->renameInitialBoss(
                    $context['user'],
                    (string) ($payload['first_name'] ?? ''),
                    (string) ($payload['last_name'] ?? '')
                ),
                'message' => 'Boss name updated.',
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
