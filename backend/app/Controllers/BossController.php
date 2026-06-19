<?php

namespace App\Controllers;

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
}
