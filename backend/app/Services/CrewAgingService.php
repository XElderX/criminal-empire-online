<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

final class CrewAgingService
{
    /**
     * Advances the game calendar by one year and processes every persistent NPC.
     * Portrait identity is preserved; only age and portrait stage may change.
     *
     * @return array<string, int>
     */
    public function advanceOneYear(): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $state = $this->lockWorldState();
            $targetYear = (int) $state['current_game_year'] + 1;
            $result = $this->processToYearWithinTransaction($targetYear);

            $updateState = $pdo->prepare(
                <<<'SQL'
                    UPDATE world_state
                    SET
                        current_game_year = ?,
                        current_game_day = 1,
                        last_age_processing_at = NOW(),
                        updated_at = NOW()
                    WHERE id = 1
                SQL
            );
            $updateState->execute([$targetYear]);

            $pdo->commit();

            return [
                ...$result,
                'current_game_year' => $targetYear,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Recalculates cached portrait stages for the current year without
     * advancing time. Safe to run repeatedly.
     *
     * @return array<string, int>
     */
    public function synchronizeCurrentYear(): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $state = $this->lockWorldState();
            $currentYear = (int) $state['current_game_year'];
            $result = $this->processToYearWithinTransaction(
                $currentYear,
                false
            );

            $pdo->commit();

            return [
                ...$result,
                'current_game_year' => $currentYear,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, int>
     */
    public function status(): array
    {
        $state = Database::pdo()->query(
            'SELECT * FROM world_state WHERE id = 1'
        )->fetch();

        if (!$state) {
            throw new RuntimeException('World state has not been initialized.');
        }

        return [
            'current_game_year' => (int) $state['current_game_year'],
            'current_game_day' => (int) $state['current_game_day'],
            'npc_count' => (int) Database::pdo()->query(
                'SELECT COUNT(*) FROM npcs'
            )->fetchColumn(),
            'portraits_assigned' => (int) Database::pdo()->query(
                'SELECT COUNT(*) FROM npcs WHERE portrait_set_key IS NOT NULL'
            )->fetchColumn(),
            'portraits_missing' => (int) Database::pdo()->query(
                'SELECT COUNT(*) FROM npcs WHERE portrait_set_key IS NULL'
            )->fetchColumn(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lockWorldState(): array
    {
        $statement = Database::pdo()->query(
            'SELECT * FROM world_state WHERE id = 1 FOR UPDATE'
        );
        $state = $statement->fetch();

        if (!$state) {
            throw new RuntimeException('World state has not been initialized.');
        }

        return $state;
    }

    /**
     * @return array<string, int>
     */
    private function processToYearWithinTransaction(
        int $targetYear,
        bool $allowAgeIncrease = true
    ): array {
        $pdo = Database::pdo();
        $ageResolver = new CrewAgeStageResolver();
        $npcs = $pdo->query(
            <<<'SQL'
                SELECT *
                FROM npcs
                ORDER BY id
                FOR UPDATE
            SQL
        )->fetchAll();
        $aged = 0;
        $stageChanges = 0;
        $unchanged = 0;

        foreach ($npcs as $npc) {
            $currentAge = (int) $npc['age'];
            $birthYear = $npc['birth_game_year'] !== null
                ? (int) $npc['birth_game_year']
                : $targetYear - $currentAge;
            $lastProcessedYear = $npc['last_age_processed_game_year'] !== null
                ? (int) $npc['last_age_processed_game_year']
                : $targetYear;

            $newAge = $currentAge;

            if ($allowAgeIncrease && $lastProcessedYear < $targetYear) {
                $newAge = max($currentAge, $targetYear - $birthYear);
            }

            $oldStage = $ageResolver->stageKey($currentAge);
            $newStage = $ageResolver->stageKey($newAge);

            $update = $pdo->prepare(
                <<<'SQL'
                    UPDATE npcs
                    SET
                        age = ?,
                        birth_game_year = ?,
                        birth_game_day = COALESCE(birth_game_day, 1),
                        last_age_processed_game_year = ?,
                        portrait_stage_cache = ?,
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            );
            $update->execute([
                $newAge,
                $birthYear,
                max($lastProcessedYear, $targetYear),
                $newStage,
                $npc['id'],
            ]);

            if ($newAge > $currentAge) {
                $aged++;
            } else {
                $unchanged++;
            }

            if ($oldStage !== $newStage) {
                $stageChanges++;
                $this->recordStageChange(
                    (int) $npc['id'],
                    $currentAge,
                    $newAge,
                    $oldStage,
                    $newStage
                );
            }
        }

        return [
            'processed' => count($npcs),
            'aged' => $aged,
            'unchanged' => $unchanged,
            'portrait_stage_changes' => $stageChanges,
        ];
    }

    private function recordStageChange(
        int $npcId,
        int $oldAge,
        int $newAge,
        string $oldStage,
        string $newStage
    ): void {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT id, user_id
                FROM player_gang_members
                WHERE npc_id = ?
                ORDER BY id DESC
                LIMIT 1
            SQL
        );
        $statement->execute([$npcId]);
        $member = $statement->fetch();

        if (!$member) {
            return;
        }

        (new CrewHistoryService())->record(
            (int) $member['id'],
            (int) $member['user_id'],
            'age_stage_changed',
            'Entered a new life stage',
            "Aged from {$oldAge} to {$newAge}; portrait stage changed from "
                . "{$oldStage} to {$newStage}.",
            [
                'old_age' => $oldAge,
                'new_age' => $newAge,
                'old_stage' => $oldStage,
                'new_stage' => $newStage,
            ]
        );
    }
}
