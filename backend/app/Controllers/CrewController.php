<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CrewService;
use App\Services\RecruitmentService;
use Throwable;

final class CrewController
{
    public function index(array $params, array $context): void
    {
        Response::json([
            'data' => (new CrewService())->members($context['user']),
        ]);
    }

    public function show(array $params, array $context): void
    {
        try {
            Response::json([
                'member' => (new CrewService())->member(
                    $context['user'],
                    (int) $params['id']
                ),
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function equip(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new CrewService())->equip(
                $context['user'],
                (int) $params['id'],
                (string) ($data['asset_type'] ?? ''),
                (int) ($data['asset_id'] ?? 0)
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function unequip(array $params, array $context): void
    {
        try {
            $result = (new CrewService())->unequip(
                $context['user'],
                (int) $params['id'],
                (int) $params['equipmentId']
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function dismiss(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new CrewService())->dismiss(
                $context['user'],
                (int) $params['id'],
                (string) ($data['reason'] ?? '')
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function history(array $params, array $context): void
    {
        try {
            Response::json([
                'data' => (new CrewService())->historyForMember(
                    $context['user'],
                    (int) $params['id']
                ),
            ]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    public function payOverdue(array $params, array $context): void
    {
        try {
            $result = (new RecruitmentService())->payOverdue(
                $context['user'],
                (int) $params['id']
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
