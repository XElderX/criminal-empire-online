<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware
{
    /** @return array{user: array<string, mixed>} */
    public function handle(): array
    {
        $token = Request::bearerToken();

        if ($token === null || $token === '') {
            Response::json(['message' => 'Unauthenticated'], 401);
        }

        $tokenHash = hash('sha256', $token);
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT user.*
                FROM api_tokens token
                JOIN users user ON user.id = token.user_id
                WHERE token.token_hash = ?
                  AND (
                    token.expires_at IS NULL
                    OR token.expires_at > NOW()
                  )
                LIMIT 1
            SQL
        );
        $statement->execute([$tokenHash]);
        $user = $statement->fetch();

        if (!$user) {
            Response::json(['message' => 'Invalid token'], 401);
        }

        return ['user' => $user];
    }
}
