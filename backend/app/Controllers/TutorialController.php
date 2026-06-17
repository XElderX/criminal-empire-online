<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\TutorialService;
use Throwable;

final class TutorialController
{
    public function state(array $params, array $context): void
    {
        Response::json([
            'tutorial' => (new TutorialService())->state($context['user']),
        ]);
    }

    public function advance(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new TutorialService())->advance(
                $context['user'],
                (string) ($data['step_code'] ?? ''),
                (bool) ($data['acknowledged'] ?? false)
            );

            Response::json(['tutorial' => $result]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function skip(array $params, array $context): void
    {
        try {
            $result = (new TutorialService())->skip($context['user']);
            Response::json(['tutorial' => $result]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reopen(array $params, array $context): void
    {
        try {
            $result = (new TutorialService())->reopenHelp($context['user']);
            Response::json(['tutorial' => $result]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
