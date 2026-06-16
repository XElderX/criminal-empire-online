<?php
namespace App\Middleware;

use App\Core\Response;

final class AdminMiddleware
{
    public function handle(): array
    {
        // AuthMiddleware must run before this in routes.
        return [];
    }

    public static function ensure(array $user): void
    {
        if (($user['role'] ?? '') !== 'admin') Response::json(['message' => 'Admin only'], 403);
    }
}
