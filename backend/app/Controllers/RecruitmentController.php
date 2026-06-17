<?php

namespace App\Controllers;

use App\Core\Response;
use App\Services\RecruitmentService;
use Throwable;

final class RecruitmentController
{
    public function index(array $params, array $context): void
    {
        Response::json([
            'data' => (new RecruitmentService())->candidates($context['user']),
        ]);
    }

    public function hire(array $params, array $context): void
    {
        try {
            $result = (new RecruitmentService())->hire(
                $context['user'],
                (int) $params['id']
            );

            Response::json($result, 201);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
