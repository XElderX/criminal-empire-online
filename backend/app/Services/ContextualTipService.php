<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;

final class ContextualTipService
{
    public function tipsForUser(int $userId, ?string $pageKey = null): array
    {
        $tips = $this->storedTips($pageKey);

        if ($tips === []) {
            $tips = array_values(GameConfig::contextualHelpTips());
            if ($pageKey !== null && $pageKey !== '') {
                $tips = array_values(array_filter($tips, static fn (array $tip): bool => ($tip['page'] ?? '') === $pageKey));
            }
        }

        $dismissed = $this->dismissedTips($userId);

        return array_values(array_map(
            static function (array $tip) use ($dismissed): array {
                $tipKey = (string) ($tip['tip_key'] ?? $tip['key'] ?? str_replace(' ', '_', (string) ($tip['page'] ?? 'general')));

                return [
                    'tip_key' => $tipKey,
                    'page_key' => (string) ($tip['page_key'] ?? $tip['page'] ?? 'dashboard'),
                    'title' => (string) ($tip['title'] ?? 'Help'),
                    'body' => (string) ($tip['body'] ?? ''),
                    'guide_section_key' => $tip['guide_section_key'] ?? null,
                    'dismissed' => in_array($tipKey, $dismissed, true),
                ];
            },
            $tips
        ));
    }

    public function dismiss(int $userId, string $tipKey): array
    {
        $tip = $this->findTip($tipKey);

        if ($tip === null) {
            throw new RuntimeException('Help tip not found.');
        }

        Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO user_help_tip_state (
                    user_id, tip_key, page_key, status, dismissed_at, created_at, updated_at
                ) VALUES (?, ?, ?, 'dismissed', NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE status = 'dismissed', dismissed_at = NOW(), updated_at = NOW()
            SQL
        )->execute([$userId, $tipKey, $tip['page_key']]);

        return ['tip_key' => $tipKey, 'status' => 'dismissed'];
    }

    public function reopen(int $userId, string $tipKey): array
    {
        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE user_help_tip_state
                SET status = 'active', dismissed_at = NULL, updated_at = NOW()
                WHERE user_id = ? AND tip_key = ?
            SQL
        )->execute([$userId, $tipKey]);

        return ['tip_key' => $tipKey, 'status' => 'active'];
    }

    private function storedTips(?string $pageKey): array
    {
        $sql = 'SELECT * FROM help_tips WHERE is_active = 1';
        $params = [];

        if ($pageKey !== null && $pageKey !== '') {
            $sql .= ' AND page_key = ?';
            $params[] = $pageKey;
        }

        $sql .= ' ORDER BY sort_order, id';
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function dismissedTips(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            "SELECT tip_key FROM user_help_tip_state WHERE user_id = ? AND status = 'dismissed'"
        );
        $statement->execute([$userId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function findTip(string $tipKey): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM help_tips WHERE tip_key = ? AND is_active = 1 LIMIT 1'
        );
        $statement->execute([$tipKey]);
        $tip = $statement->fetch();

        if ($tip) {
            return $tip;
        }

        foreach (GameConfig::contextualHelpTips() as $key => $fallback) {
            if ($key === $tipKey) {
                return [
                    'tip_key' => $key,
                    'page_key' => $fallback['page'],
                    'title' => $fallback['title'],
                    'body' => $fallback['body'],
                    'guide_section_key' => null,
                ];
            }
        }

        return null;
    }
}
