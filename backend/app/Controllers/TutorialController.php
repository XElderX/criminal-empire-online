<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ContextualTipService;
use App\Services\HelpGuideService;
use App\Services\TutorialService;
use Throwable;

final class TutorialController
{
    public function state(array $params, array $context): void
    {
        Response::json(['tutorial' => (new TutorialService())->state($context['user'])]);
    }

    public function current(array $params, array $context): void
    {
        Response::json(['tutorial' => (new TutorialService())->current($context['user'])]);
    }

    public function steps(array $params, array $context): void
    {
        Response::json((new TutorialService())->steps($context['user']));
    }

    public function recordObjective(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $tutorial = (new TutorialService())->recordObjective(
                $context['user'],
                (string) ($data['action_type'] ?? ''),
                is_array($data['payload'] ?? null) ? $data['payload'] : []
            );

            Response::json(['tutorial' => $tutorial]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
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

    public function resetDev(array $params, array $context): void
    {
        try {
            $result = (new TutorialService())->resetDev($context['user']);
            Response::json(['tutorial' => $result]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 403);
        }
    }

    public function guide(array $params, array $context): void
    {
        Response::json(['guide' => (new HelpGuideService())->guide()]);
    }

    public function tips(array $params, array $context): void
    {
        $pageKey = isset($_GET['page']) ? (string) $_GET['page'] : null;

        Response::json([
            'tips' => (new ContextualTipService())->tipsForUser(
                (int) $context['user']['id'],
                $pageKey
            ),
        ]);
    }

    public function dismissTip(array $params, array $context): void
    {
        try {
            Response::json([
                'tip' => (new ContextualTipService())->dismiss(
                    (int) $context['user']['id'],
                    (string) $params['tipKey']
                ),
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reopenTip(array $params, array $context): void
    {
        try {
            Response::json([
                'tip' => (new ContextualTipService())->reopen(
                    (int) $context['user']['id'],
                    (string) $params['tipKey']
                ),
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
