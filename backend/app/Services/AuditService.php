<?php
namespace App\Services;

use App\Core\Database;

final class AuditService
{
    public static function log(?int $userId, string $action, array $payload = []): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO audit_logs (user_id, action, payload, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$userId, $action, json_encode($payload)]);
    }
}
