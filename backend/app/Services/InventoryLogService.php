<?php

namespace App\Services;

use App\Core\Database;

final class InventoryLogService
{
    public function record(int $userId, string $actionType, string $description, array $metadata = []): void
    {
        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO inventory_logs (
                    user_id, character_type, character_id, item_key, asset_type, asset_id,
                    action_type, quantity, from_holder, to_holder, description, metadata, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        )->execute([
            $userId,
            $metadata['character_type'] ?? null,
            $metadata['character_id'] ?? null,
            $metadata['item_key'] ?? null,
            $metadata['asset_type'] ?? null,
            $metadata['asset_id'] ?? null,
            $actionType,
            (int) ($metadata['quantity'] ?? 1),
            $metadata['from_holder'] ?? null,
            $metadata['to_holder'] ?? null,
            $description,
            json_encode($metadata, JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function paginated(array $user, array $query): array
    {
        return (new PaginationService())->query(
            'SELECT * FROM inventory_logs WHERE user_id = ? ORDER BY id DESC',
            'SELECT COUNT(*) FROM inventory_logs WHERE user_id = ?',
            [(int) $user['id']],
            $query
        );
    }
}
