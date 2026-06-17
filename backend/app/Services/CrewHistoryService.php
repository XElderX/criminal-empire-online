<?php

namespace App\Services;

use App\Core\Database;

final class CrewHistoryService
{
    public function record(
        int $memberId,
        int $userId,
        string $eventType,
        string $title,
        string $description,
        array $metadata = [],
        ?int $dirtyJobRunId = null
    ): void {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO crew_history (
                    gang_member_id,
                    user_id,
                    event_type,
                    title,
                    description,
                    related_dirty_job_run_id,
                    metadata,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        );

        $statement->execute([
            $memberId,
            $userId,
            $eventType,
            $title,
            $description,
            $dirtyJobRunId,
            json_encode($metadata),
        ]);
    }
}
