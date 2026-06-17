<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ItemService;
use Throwable;

final class ItemController
{
    public function shop(array $params, array $context): void
    {
        Response::json([
            'data' => (new ItemService())->shop($context['user']),
        ]);
    }

    public function buy(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new ItemService())->buy(
                $context['user'],
                (int) $params['id'],
                (int) ($data['quantity'] ?? 1)
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function inventory(array $params, array $context): void
    {
        Response::json((new ItemService())->inventory($context['user']));
    }
}
