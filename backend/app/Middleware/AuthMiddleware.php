<?php
namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware
{
    public function handle(): array
    {
        $token = Request::bearerToken();
        if (!$token) Response::json(['message' => 'Unauthenticated'], 401);
        $hash = hash('sha256', $token);
        $stmt = Database::pdo()->prepare('SELECT u.* FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND (t.expires_at IS NULL OR t.expires_at > NOW()) LIMIT 1');
        $stmt->execute([$hash]);
        $user = $stmt->fetch();
        if (!$user) Response::json(['message' => 'Invalid token'], 401);
        return ['user' => $user];
    }
}
