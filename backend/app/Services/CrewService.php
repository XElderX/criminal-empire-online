<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class CrewService
{
    public function members(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    member.*,
                    npc.first_name,
                    npc.last_name,
                    npc.nickname,
                    npc.age,
                    npc.biography,
                    npc.background,
                    npc.occupation,
                    npc.personal_cash,
                    npc.home_territory_id,
                    territory.name AS territory_name
                FROM player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                LEFT JOIN territories territory
                    ON territory.id = npc.home_territory_id
                WHERE member.user_id = ?
                  AND member.status <> 'dismissed'
                ORDER BY member.id
            SQL
        );

        $statement->execute([$user['id']]);
        $members = $statement->fetchAll();

        foreach ($members as &$member) {
            $member['traits'] = $this->traits((int) $member['npc_id']);
            $member['equipment'] = $this->equipment((int) $member['id']);
            $member['recent_history'] = $this->history((int) $member['id'], 5);
        }

        return $members;
    }

    public function member(array $user, int $memberId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    member.*,
                    npc.first_name,
                    npc.last_name,
                    npc.nickname,
                    npc.age,
                    npc.biography,
                    npc.background,
                    npc.occupation,
                    npc.personal_cash,
                    territory.name AS territory_name
                FROM player_gang_members member
                JOIN npcs npc ON npc.id = member.npc_id
                LEFT JOIN territories territory
                    ON territory.id = npc.home_territory_id
                WHERE member.id = ?
                  AND member.user_id = ?
                LIMIT 1
            SQL
        );

        $statement->execute([$memberId, $user['id']]);
        $member = $statement->fetch();

        if (!$member) {
            throw new RuntimeException('Crew member not found.');
        }

        $member['traits'] = $this->traits((int) $member['npc_id']);
        $member['equipment'] = $this->equipment((int) $member['id']);
        $member['history'] = $this->history((int) $member['id'], 100);

        return $member;
    }

    public function equip(
        array $user,
        int $memberId,
        string $assetType,
        int $assetId
    ): array {
        if (!in_array($assetType, ['item', 'weapon'], true)) {
            throw new RuntimeException('Unsupported equipment type.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $member = $this->lockMember($memberId, (int) $user['id']);

            if (in_array($member['status'], ['dismissed', 'dead', 'arrested'], true)) {
                throw new RuntimeException('This crew member cannot use equipment.');
            }

            $asset = $this->loadAsset($assetType, $assetId);
            $slot = (string) $asset['equipment_slot'];

            if ($slot === '') {
                throw new RuntimeException('This asset cannot be equipped.');
            }

            $existingStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT *
                    FROM crew_equipment
                    WHERE gang_member_id = ?
                      AND equipment_slot = ?
                    FOR UPDATE
                SQL
            );
            $existingStatement->execute([$memberId, $slot]);
            $existing = $existingStatement->fetch();

            if (
                $existing
                && $existing['asset_type'] === $assetType
                && (int) $existing['asset_id'] === $assetId
            ) {
                $pdo->commit();

                return [
                    'message' => 'This item is already equipped.',
                    'equipment' => $existing,
                ];
            }

            if ($existing) {
                $pdo->prepare(
                    'DELETE FROM crew_equipment WHERE id = ?'
                )->execute([$existing['id']]);
            }

            $ownedQuantity = $this->ownedQuantity(
                (int) $user['id'],
                $assetType,
                $assetId
            );
            $equippedQuantity = $this->equippedQuantity(
                (int) $user['id'],
                $assetType,
                $assetId
            );

            if ($equippedQuantity >= $ownedQuantity) {
                throw new RuntimeException('No unequipped copy of this item is available.');
            }

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO crew_equipment (
                        user_id,
                        gang_member_id,
                        asset_type,
                        asset_id,
                        equipment_slot,
                        durability,
                        equipped_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                SQL
            );

            $insert->execute([
                $user['id'],
                $memberId,
                $assetType,
                $assetId,
                $slot,
                $asset['base_durability'],
            ]);

            $equipmentId = (int) $pdo->lastInsertId();

            (new CrewHistoryService())->record(
                $memberId,
                (int) $user['id'],
                'equipment',
                'Equipment changed',
                "Equipped {$asset['name']} in the {$slot} slot.",
                [
                    'asset_type' => $assetType,
                    'asset_id' => $assetId,
                    'equipment_slot' => $slot,
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Equipment assigned.',
                'equipment_id' => $equipmentId,
                'item_name' => $asset['name'],
                'slot' => $slot,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function unequip(array $user, int $memberId, int $equipmentId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $this->lockMember($memberId, (int) $user['id']);

            $statement = $pdo->prepare(
                <<<'SQL'
                    SELECT *
                    FROM crew_equipment
                    WHERE id = ?
                      AND gang_member_id = ?
                      AND user_id = ?
                    FOR UPDATE
                SQL
            );
            $statement->execute([$equipmentId, $memberId, $user['id']]);
            $equipment = $statement->fetch();

            if (!$equipment) {
                throw new RuntimeException('Equipped item not found.');
            }

            $pdo->prepare(
                'DELETE FROM crew_equipment WHERE id = ?'
            )->execute([$equipmentId]);

            (new CrewHistoryService())->record(
                $memberId,
                (int) $user['id'],
                'equipment',
                'Equipment removed',
                'An equipped item was returned to the player inventory.',
                [
                    'asset_type' => $equipment['asset_type'],
                    'asset_id' => $equipment['asset_id'],
                ]
            );

            $pdo->commit();

            return ['message' => 'Equipment returned to inventory.'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function dismiss(
        array $user,
        int $memberId,
        string $reason
    ): array {
        $reason = trim($reason);

        if ($reason === '') {
            $reason = 'Dismissed by the player.';
        }

        if (strlen($reason) > 255) {
            throw new RuntimeException('Dismissal reason is too long.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $member = $this->lockMember($memberId, (int) $user['id']);

            if ($member['status'] === 'dismissed') {
                throw new RuntimeException('This member has already been dismissed.');
            }

            if (
                $member['status'] === 'busy'
                || $member['current_assignment_id'] !== null
            ) {
                throw new RuntimeException(
                    'Cancel or resolve the active assignment before dismissal.'
                );
            }

            $pdo->prepare(
                'DELETE FROM crew_equipment WHERE gang_member_id = ?'
            )->execute([$memberId]);

            $updateMember = $pdo->prepare(
                <<<'SQL'
                    UPDATE player_gang_members
                    SET
                        status = 'dismissed',
                        current_assignment_type = NULL,
                        current_assignment_id = NULL,
                        dismissed_at = NOW(),
                        dismissal_reason = ?,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            );
            $updateMember->execute([$reason, $memberId]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE npcs
                    SET
                        role = 'recruit',
                        status = 'unemployed',
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$member['npc_id']]);

            if ($member['recruitment_candidate_id'] !== null) {
                $candidateUpdate = $pdo->prepare(
                    <<<'SQL'
                        UPDATE recruitment_candidates
                        SET
                            status = 'available',
                            available_from = DATE_ADD(NOW(), INTERVAL 14 DAY),
                            expires_at = DATE_ADD(NOW(), INTERVAL 44 DAY),
                            hired_by_user_id = NULL,
                            hired_at = NULL
                        WHERE id = ?
                    SQL
                );
                $candidateUpdate->execute([$member['recruitment_candidate_id']]);
            }

            (new CrewHistoryService())->record(
                $memberId,
                (int) $user['id'],
                'dismissed',
                'Dismissed from the crew',
                $reason,
                [
                    'unpaid_salary' => (int) $member['unpaid_salary'],
                    'loyalty_at_dismissal' => (int) $member['loyalty'],
                    'time_served_from' => $member['recruited_at'],
                ]
            );

            AuditService::log(
                (int) $user['id'],
                'crew.dismiss',
                [
                    'member_id' => $memberId,
                    'reason' => $reason,
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Crew member dismissed.',
                'member_id' => $memberId,
                'equipment_returned' => true,
                'world_return_delay_days' => 14,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function historyForMember(array $user, int $memberId): array
    {
        $this->member($user, $memberId);

        return $this->history($memberId, 200);
    }

    private function lockMember(int $memberId, int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM player_gang_members
                WHERE id = ?
                  AND user_id = ?
                FOR UPDATE
            SQL
        );
        $statement->execute([$memberId, $userId]);
        $member = $statement->fetch();

        if (!$member) {
            throw new RuntimeException('Crew member not found.');
        }

        return $member;
    }

    private function loadAsset(string $assetType, int $assetId): array
    {
        if ($assetType === 'item') {
            $statement = Database::pdo()->prepare(
                <<<'SQL'
                    SELECT
                        id,
                        name,
                        equipment_slot,
                        max_durability AS base_durability,
                        effects
                    FROM item_definitions
                    WHERE id = ?
                      AND active = 1
                SQL
            );
        } else {
            $statement = Database::pdo()->prepare(
                <<<'SQL'
                    SELECT
                        id,
                        name,
                        equipment_slot,
                        base_durability,
                        effects
                    FROM weapons
                    WHERE id = ?
                SQL
            );
        }

        $statement->execute([$assetId]);
        $asset = $statement->fetch();

        if (!$asset) {
            throw new RuntimeException('Equipment asset not found.');
        }

        return $asset;
    }

    private function ownedQuantity(
        int $userId,
        string $assetType,
        int $assetId
    ): int {
        if ($assetType === 'item') {
            $statement = Database::pdo()->prepare(
                <<<'SQL'
                    SELECT quantity
                    FROM user_items
                    WHERE user_id = ?
                      AND item_definition_id = ?
                    FOR UPDATE
                SQL
            );
        } else {
            $statement = Database::pdo()->prepare(
                <<<'SQL'
                    SELECT quantity
                    FROM user_weapons
                    WHERE user_id = ?
                      AND weapon_id = ?
                    FOR UPDATE
                SQL
            );
        }

        $statement->execute([$userId, $assetId]);

        return (int) ($statement->fetchColumn() ?: 0);
    }

    private function equippedQuantity(
        int $userId,
        string $assetType,
        int $assetId
    ): int {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM crew_equipment
                WHERE user_id = ?
                  AND asset_type = ?
                  AND asset_id = ?
            SQL
        );
        $statement->execute([$userId, $assetType, $assetId]);

        return (int) $statement->fetchColumn();
    }

    private function equipment(int $memberId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    equipment.*,
                    COALESCE(item.name, weapon.name) AS name,
                    COALESCE(item.description, weapon.class) AS description,
                    COALESCE(item.effects, weapon.effects) AS effects
                FROM crew_equipment equipment
                LEFT JOIN item_definitions item
                    ON equipment.asset_type = 'item'
                    AND item.id = equipment.asset_id
                LEFT JOIN weapons weapon
                    ON equipment.asset_type = 'weapon'
                    AND weapon.id = equipment.asset_id
                WHERE equipment.gang_member_id = ?
                ORDER BY equipment.equipment_slot
            SQL
        );
        $statement->execute([$memberId]);
        $equipment = $statement->fetchAll();

        foreach ($equipment as &$entry) {
            $entry['effects'] = json_decode((string) $entry['effects'], true) ?: [];
        }

        return $equipment;
    }

    private function history(int $memberId, int $limit): array
    {
        $limit = max(1, min(200, $limit));
        $statement = Database::pdo()->prepare(
            <<<SQL
                SELECT *
                FROM crew_history
                WHERE gang_member_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT {$limit}
            SQL
        );
        $statement->execute([$memberId]);
        $history = $statement->fetchAll();

        foreach ($history as &$entry) {
            $entry['metadata'] = json_decode((string) $entry['metadata'], true) ?: [];
        }

        return $history;
    }

    private function traits(int $npcId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    trait.code,
                    trait.name,
                    trait.polarity,
                    trait.description,
                    trait.effects
                FROM npc_trait_assignments assignment
                JOIN npc_traits trait ON trait.id = assignment.trait_id
                WHERE assignment.npc_id = ?
            SQL
        );
        $statement->execute([$npcId]);
        $traits = $statement->fetchAll();

        foreach ($traits as &$trait) {
            $trait['effects'] = json_decode((string) $trait['effects'], true) ?: [];
        }

        return $traits;
    }
}
