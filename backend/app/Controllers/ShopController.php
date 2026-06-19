<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ShopService;
use App\Services\ShopTransactionService;
use RuntimeException;
use Throwable;

final class ShopController
{
    public function index(array $params = [], array $context = []): void
    {
        try {
            Response::json((new ShopService())->list($context['user']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function show(array $params, array $context): void
    {
        try {
            Response::json((new ShopService())->detail($context['user'], (string) $params['slug']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function items(array $params, array $context): void
    {
        try {
            $detail = (new ShopService())->detail($context['user'], (string) $params['slug']);
            Response::json(['data' => $detail['items']]);
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function buy(array $params, array $context): void
    {
        $payload = Request::json();

        try {
            Response::json((new ShopTransactionService())->buy(
                $context['user'],
                (string) $params['slug'],
                (string) ($payload['item_key'] ?? ''),
                (int) ($payload['quantity'] ?? 1)
            ));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function sell(array $params, array $context): void
    {
        $payload = Request::json();

        try {
            Response::json((new ShopTransactionService())->sell(
                $context['user'],
                (string) $params['slug'],
                (string) ($payload['item_key'] ?? ''),
                (int) ($payload['quantity'] ?? 1)
            ));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function transactions(array $params, array $context): void
    {
        try {
            Response::json((new ShopService())->history($context['user'], (string) $params['slug']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function locationShops(array $params, array $context): void
    {
        try {
            Response::json((new ShopService())->shopsForLocation($context['user'], (string) $params['slug']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 404);
        }
    }

    /**
     * Legacy global weapon catalog remains read-only for old clients.
     * v0.6.5 gameplay buys gear through map shops instead.
     */
    public function weapons(array $params = [], array $context = []): void
    {
        if (($context['user']['role'] ?? 'player') !== 'admin') {
            Response::json([
                'data' => [],
                'legacy_purchase_disabled' => true,
                'message' => 'Weapons are no longer bought from the global inventory page. Travel to a map shop or dealer.',
            ]);
            return;
        }

        $weapons = Database::pdo()->query('SELECT * FROM weapons ORDER BY price ASC')->fetchAll();
        foreach ($weapons as &$weapon) {
            $weapon['effects'] = json_decode((string) ($weapon['effects'] ?? ''), true) ?: [];
        }

        Response::json(['data' => $weapons, 'legacy_purchase_disabled' => false]);
    }

    public function buyWeapon(array $params, array $context): void
    {
        if (($context['user']['role'] ?? 'player') !== 'admin') {
            Response::json([
                'message' => 'Global weapon buying is disabled for players. Travel to a map shop or dealer.',
            ], 422);
            return;
        }

        Response::json(['message' => 'Admin legacy weapon buying is disabled in v0.6.5. Use map shop catalogs.'], 422);
    }

    private function fail(string $message): never
    {
        throw new RuntimeException($message);
    }
}
