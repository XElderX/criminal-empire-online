<?php

namespace App\Controllers;

use App\Core\Response;
use App\Services\HeatService;
use Throwable;

final class HeatController
{
    public function layLow(array $params, array $context): void
    {
        try {
            $result = (new HeatService())->layLow($context['user']);
            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
