<?php

namespace App\Services;

use App\Core\Database;

final class CrewRecoveryService
{
    public function process(): array
    {
        $recoveredStatement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE player_gang_members
                SET
                    status = 'active',
                    health = LEAST(max_health, health + 25),
                    recovering_until = NULL,
                    updated_at = NOW()
                WHERE status IN ('injured', 'recovering')
                  AND recovering_until IS NOT NULL
                  AND recovering_until <= NOW()
            SQL
        );
        $recoveredStatement->execute();

        $releasedStatement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                SET
                    member.status = 'active',
                    member.arrested_until = NULL,
                    member.updated_at = NOW(),
                    npc.arrested_until = NULL,
                    npc.status = 'employed',
                    npc.updated_at = NOW()
                WHERE member.status = 'arrested'
                  AND member.arrested_until IS NOT NULL
                  AND member.arrested_until <= NOW()
            SQL
        );
        $releasedStatement->execute();

        return [
            'members_recovered' => $recoveredStatement->rowCount(),
            'members_released' => $releasedStatement->rowCount(),
        ];
    }
}
