<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class EquipmentValidationService
{
    public function validateCrewMember(array $user, string $characterType, int $characterId): void
    {
        if ($characterType === 'boss' && $characterId === 0) return;
        if ($characterType !== 'crew') {
            throw new RuntimeException('Invalid character type.');
        }
        $statement = Database::pdo()->prepare('SELECT id FROM player_gang_members WHERE id = ? AND user_id = ? AND status != \'dismissed\' LIMIT 1');
        $statement->execute([$characterId, $user['id']]);
        if (!$statement->fetch()) {
            throw new RuntimeException('Crew member not found for this player.');
        }
    }

    public function lockItem(int $userId, int $itemId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT inventory.*, item.*
                FROM user_items inventory
                JOIN item_definitions item ON item.id = inventory.item_definition_id
                WHERE inventory.user_id = ? AND item.id = ? AND inventory.quantity > 0
                LIMIT 1 FOR UPDATE
            SQL
        );
        $statement->execute([$userId, $itemId]);
        $item = $statement->fetch();
        if (!$item) {
            throw new RuntimeException('Player cannot equip another player\'s item or an unowned item.');
        }
        return $item;
    }
}
