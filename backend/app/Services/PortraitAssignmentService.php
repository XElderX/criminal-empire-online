<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class PortraitAssignmentService
{
    public function __construct(
        private readonly ?PortraitManifestService $manifest = null,
        private readonly ?CrewAgeStageResolver $ageResolver = null,
        private readonly ?RandomSource $random = null
    ) {
    }

    /**
     * Assigns a permanent portrait identity to one NPC.
     * Existing identities are never replaced.
     *
     * @param array<int, string> $excludedKeys
     * @return array<string, mixed>
     */
    public function assignToNpc(int $npcId, array $excludedKeys = []): array
    {
        $pdo = Database::pdo();
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $statement = $pdo->prepare(
                <<<'SQL'
                    SELECT *
                    FROM npcs
                    WHERE id = ?
                    FOR UPDATE
                SQL
            );
            $statement->execute([$npcId]);
            $npc = $statement->fetch();

            if (!$npc) {
                throw new RuntimeException('NPC not found for portrait assignment.');
            }

            if (!empty($npc['portrait_set_key'])) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }

                return $npc;
            }

            $manifest = $this->manifest ?? new PortraitManifestService();
            $gender = $manifest->normalizeGender($npc['gender'] ?? null);
            $sets = $manifest->enabledSets($gender);

            if ($sets === []) {
                throw new RuntimeException(
                    'No enabled portrait sets match this NPC gender.'
                );
            }

            $preferredSets = array_filter(
                $sets,
                static fn (array $set): bool => !in_array(
                    $set['key'],
                    $excludedKeys,
                    true
                )
            );

            if ($preferredSets !== []) {
                $sets = $preferredSets;
            }

            $sets = array_values($sets);
            $random = $this->random ?? new SecureRandomSource();
            $selectedIndex = $random->integer(0, count($sets) - 1);
            $selected = $sets[$selectedIndex];
            $ageResolver = $this->ageResolver ?? new CrewAgeStageResolver();
            $stage = $ageResolver->stageKey((int) $npc['age']);

            $update = $pdo->prepare(
                <<<'SQL'
                    UPDATE npcs
                    SET
                        portrait_set_key = ?,
                        portrait_stage_cache = ?,
                        portrait_focal_x = ?,
                        portrait_focal_y = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND portrait_set_key IS NULL
                SQL
            );
            $update->execute([
                $selected['key'],
                $stage,
                $selected['focal_x'] ?? 50,
                $selected['focal_y'] ?? 50,
                $npcId,
            ]);

            $npc['portrait_set_key'] = $selected['key'];
            $npc['portrait_stage_cache'] = $stage;
            $npc['portrait_focal_x'] = $selected['focal_x'] ?? 50;
            $npc['portrait_focal_y'] = $selected['focal_y'] ?? 50;

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $npc;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Backfills all NPCs that do not yet have a portrait identity.
     * Male NPCs only receive male portraits and female NPCs only receive
     * female portraits. Unknown gender values are reported as skipped.
     *
     * @return array<string, mixed>
     */
    public function backfillAll(): array
    {
        $statement = Database::pdo()->query(
            <<<'SQL'
                SELECT id, gender
                FROM npcs
                WHERE portrait_set_key IS NULL
                ORDER BY id
            SQL
        );
        $rows = $statement->fetchAll();
        $usedByGender = [
            'male' => [],
            'female' => [],
        ];
        $assigned = 0;
        $assignedByGender = [
            'male' => 0,
            'female' => 0,
        ];
        $skipped = [];

        foreach ($rows as $row) {
            $gender = (new PortraitManifestService())->normalizeGender(
                $row['gender'] ?? null
            );

            if ($gender === null) {
                $skipped[] = [
                    'npc_id' => (int) $row['id'],
                    'reason' => 'Unsupported or missing gender value.',
                ];
                continue;
            }

            $npc = $this->assignToNpc(
                (int) $row['id'],
                $usedByGender[$gender]
            );
            $usedByGender[$gender][] = (string) $npc['portrait_set_key'];
            $assignedByGender[$gender]++;
            $assigned++;
        }

        return [
            'assigned' => $assigned,
            'assigned_by_gender' => $assignedByGender,
            'skipped' => $skipped,
            'remaining_without_portrait' => (int) Database::pdo()->query(
                'SELECT COUNT(*) FROM npcs WHERE portrait_set_key IS NULL'
            )->fetchColumn(),
        ];
    }
}
