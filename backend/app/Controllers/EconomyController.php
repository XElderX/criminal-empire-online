<?php

namespace App\Controllers;

use App\Core\Response;
use App\Middleware\AdminMiddleware;
use App\Services\EconomyStatusService;

final class EconomyController
{
    public function status(array $params, array $context): void
    {
        AdminMiddleware::ensure($context['user']);

        Response::json([
            'data' => (new EconomyStatusService())->report(),
        ]);
    }
}
