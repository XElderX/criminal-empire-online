<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ItemService;
use App\Services\ShopCatalogService;
use Throwable;

final class ItemController
{
    public function shop(array $params, array $context): void
    {
        $legacyItems = (new ItemService())->shop($context['user']);
        $itemKeys = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['code'] ?? ''),
            $legacyItems
        )));

        Response::json([
            'data' => [],
            'legacy_purchase_disabled' => true,
            'message' => 'Inventory buying moved to map shops in v0.6.5. Travel to a shop to buy gear.',
            'possible_sources' => (new ShopCatalogService())->sourceMapForItems($itemKeys),
        ]);
    }

    public function buy(array $params, array $context): void
    {
        if (($context['user']['role'] ?? 'player') !== 'admin') {
            Response::json([
                'message' => 'Global inventory buying is disabled. Travel to a map shop to buy equipment.',
            ], 422);
            return;
        }

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
