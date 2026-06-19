<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class UpdateNoticeService
{
    public function pending(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT notice.*
                FROM update_notices notice
                LEFT JOIN user_update_notice_acknowledgements ack
                  ON ack.notice_id = notice.id
                  AND ack.user_id = ?
                WHERE notice.active = 1
                  AND ack.id IS NULL
                ORDER BY notice.id DESC
                LIMIT 1
            SQL
        );
        $statement->execute([$userId]);
        $notice = $statement->fetch();

        return ['notice' => $notice ?: null];
    }

    public function acknowledge(int $userId, int $noticeId): array
    {
        $statement = Database::pdo()->prepare('SELECT id FROM update_notices WHERE id = ? AND active = 1 LIMIT 1');
        $statement->execute([$noticeId]);

        if (!$statement->fetchColumn()) {
            throw new RuntimeException('Update notice not found.');
        }

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO user_update_notice_acknowledgements (user_id, notice_id, acknowledged_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE acknowledged_at = VALUES(acknowledged_at)
            SQL
        )->execute([$userId, $noticeId]);

        return ['message' => 'Update notice acknowledged.'];
    }
}
