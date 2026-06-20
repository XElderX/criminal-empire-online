<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\WarehouseService;
use App\Services\PaginationService;
use Throwable;

final class WarehouseController
{
    public function index(array $params, array $context): void
    {
        Response::json((new WarehouseService())->overview($context['user']));
    }

    public function listings(array $params, array $context): void
    {
        Response::json([
            'data' => (new WarehouseService())->listings($context['user']),
        ]);
    }

    public function purchase(array $params, array $context): void
    {
        try {
            $result = (new WarehouseService())->purchase(
                $context['user'],
                (int) $params['id']
            );

            Response::json($result, 201);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function transfer(array $params, array $context): void
    {
        $data = Request::json();

        try {
            $result = (new WarehouseService())->transfer(
                $context['user'],
                (int) $params['id'],
                (string) ($data['direction'] ?? ''),
                (string) ($data['asset_type'] ?? ''),
                (int) ($data['asset_id'] ?? 0),
                (int) ($data['quantity'] ?? 0)
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function storeVehicle(array $params, array $context): void
    {
        try {
            $result = (new WarehouseService())->storeVehicle(
                $context['user'],
                (int) $params['id'],
                (int) $params['vehicleId']
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function removeVehicle(array $params, array $context): void
    {
        try {
            $result = (new WarehouseService())->removeVehicle(
                $context['user'],
                (int) $params['id'],
                (int) $params['vehicleId']
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function purchaseUpgrade(array $params, array $context): void
    {
        try {
            $result = (new WarehouseService())->purchaseUpgrade(
                $context['user'],
                (int) $params['id'],
                (int) $params['upgradeId']
            );

            Response::json($result);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function logs(array $params, array $context): void
    {
        try {
            Response::json((new PaginationService())->query(
                "SELECT * FROM inventory_logs WHERE user_id = ? AND (action_type LIKE 'warehouse%' OR to_holder = 'warehouse' OR from_holder = 'warehouse') ORDER BY id DESC",
                "SELECT COUNT(*) FROM inventory_logs WHERE user_id = ? AND (action_type LIKE 'warehouse%' OR to_holder = 'warehouse' OR from_holder = 'warehouse')",
                [(int) $context['user']['id']],
                $_GET
            ));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

}
