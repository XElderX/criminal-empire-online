<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use Throwable;

final class AuthController
{
    public function register(array $params = [], array $context = []): void
    {
        try { Response::json((new AuthService())->register(Request::json()), 201); }
        catch (Throwable $e) { Response::json(['message' => $e->getMessage()], 422); }
    }

    public function login(array $params = [], array $context = []): void
    {
        $data = Request::json();
        try { Response::json((new AuthService())->login($data['email'] ?? '', $data['password'] ?? '')); }
        catch (Throwable $e) { Response::json(['message' => $e->getMessage()], 422); }
    }

    public function me(array $params, array $context): void
    {
        $user = $context['user']; unset($user['password']);
        Response::json(['user' => $user]);
    }
}
