<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;

final class DirtyJobGeneratorService
{
    private const TARGET_NAMES = [
        'a tired apartment block',
        'a poorly watched service alley',
        'a small family shop',
        'an aging industrial yard',
        'a quiet delivery route',
        'a neglected storage unit',
    ];

    public function ensureForUser(array $user): void
    {
        $this->expireOldOpportunities((int) $user['id']);

        $availableCount = $this->availableCount((int) $user['id']);
        $needed = max(
            0,
            GameConfig::DIRTY_JOB_OPPORTUNITY_TARGET - $availableCount
        );

        if ($needed === 0) {
            return;
        }

        $templates = $this->eligibleTemplates($user);
        $contacts = $this->contacts();
        $territories = $this->territories();

        foreach ($templates as $template) {
            if ($needed <= 0) {
                break;
            }

            if ($this->hasOpenOpportunity((int) $user['id'], (int) $template['id'])) {
                continue;
            }

            $contact = $this->matchingContact(
                (string) $template['category'],
                $contacts
            );
            $territory = $this->chooseTerritory($user, $territories);
            $targetName = self::TARGET_NAMES[array_rand(self::TARGET_NAMES)];

            $statement = Database::pdo()->prepare(
                <<<'SQL'
                    INSERT INTO dirty_job_opportunities (
                        user_id,
                        template_id,
                        contact_id,
                        territory_id,
                        target_npc_id,
                        target_business_id,
                        title_override,
                        narrative_variables,
                        reward_multiplier,
                        risk_modifier,
                        status,
                        available_from,
                        expires_at,
                        created_at,
                        updated_at
                    ) VALUES (
                        ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, ?,
                        'available', NOW(), DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW()
                    )
                SQL
            );

            $statement->execute([
                $user['id'],
                $template['id'],
                $contact['id'] ?? null,
                $territory['id'],
                json_encode([
                    'target_name' => $targetName,
                    'district_name' => $territory['name'],
                    'contact_name' => $contact['display_name'] ?? 'A local contact',
                ]),
                $this->rewardMultiplier($territory),
                $this->riskModifier($territory),
            ]);

            $needed--;
        }
    }

    public function refreshForAllUsers(): array
    {
        $users = Database::pdo()->query(
            <<<'SQL'
                SELECT *
                FROM users
                WHERE role = 'player'
                ORDER BY id
            SQL
        )->fetchAll();

        foreach ($users as $user) {
            $this->ensureForUser($user);
        }

        return [
            'users_processed' => count($users),
            'available_opportunities' => (int) Database::pdo()
                ->query(
                    "SELECT COUNT(*) FROM dirty_job_opportunities WHERE status = 'available' AND expires_at > NOW()"
                )
                ->fetchColumn(),
        ];
    }

    public function expireOldOpportunities(?int $userId = null): int
    {
        if ($userId === null) {
            $statement = Database::pdo()->prepare(
                <<<'SQL'
                    UPDATE dirty_job_opportunities
                    SET status = 'expired', updated_at = NOW()
                    WHERE status = 'available'
                      AND expires_at <= NOW()
                SQL
            );
            $statement->execute();

            return $statement->rowCount();
        }

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                UPDATE dirty_job_opportunities
                SET status = 'expired', updated_at = NOW()
                WHERE user_id = ?
                  AND status = 'available'
                  AND expires_at <= NOW()
            SQL
        );
        $statement->execute([$userId]);

        return $statement->rowCount();
    }

    private function eligibleTemplates(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM dirty_job_templates
                WHERE active = 1
                  AND min_level <= ?
                  AND min_reputation <= ?
                ORDER BY tier, id
            SQL
        );
        $statement->execute([
            $user['level'] ?? 1,
            $user['reputation'] ?? 0,
        ]);

        return $statement->fetchAll();
    }

    private function contacts(): array
    {
        $rows = Database::pdo()->query(
            <<<'SQL'
                SELECT
                    contact.*,
                    npc.first_name,
                    npc.last_name,
                    npc.nickname,
                    npc.biography,
                    npc.home_territory_id
                FROM npc_contacts contact
                JOIN npcs npc ON npc.id = contact.npc_id
                WHERE contact.active = 1
                ORDER BY contact.id
            SQL
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['job_categories'] = json_decode(
                (string) $row['job_categories'],
                true
            ) ?: [];
            $row['display_name'] = trim(
                $row['first_name']
                . ' '
                . ($row['nickname'] ? "“{$row['nickname']}” " : '')
                . $row['last_name']
            );
        }

        return $rows;
    }

    private function territories(): array
    {
        return Database::pdo()->query(
            <<<'SQL'
                SELECT *
                FROM territories
                ORDER BY wealth, id
            SQL
        )->fetchAll();
    }

    private function matchingContact(string $category, array $contacts): ?array
    {
        $matches = array_values(array_filter(
            $contacts,
            static fn (array $contact): bool => in_array(
                $category,
                $contact['job_categories'],
                true
            )
        ));

        if ($matches === []) {
            return $contacts[0] ?? null;
        }

        return $matches[array_rand($matches)];
    }

    private function chooseTerritory(array $user, array $territories): array
    {
        foreach ($territories as $territory) {
            if ((int) $territory['id'] === (int) ($user['home_territory_id'] ?? 0)) {
                return $territory;
            }
        }

        return $territories[array_rand($territories)];
    }

    private function rewardMultiplier(array $territory): float
    {
        $wealth = (int) ($territory['wealth'] ?? 50);
        $multiplier = 0.8 + ($wealth / 250);

        return round(max(0.8, min(1.25, $multiplier)), 3);
    }

    private function riskModifier(array $territory): int
    {
        $police = (int) ($territory['police_presence'] ?? 50);
        $crime = (int) ($territory['crime_rate'] ?? 50);

        return (int) round(($police - $crime) / 10);
    }

    private function availableCount(int $userId): int
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_opportunities
                WHERE user_id = ?
                  AND status = 'available'
                  AND available_from <= NOW()
                  AND expires_at > NOW()
            SQL
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    private function hasOpenOpportunity(int $userId, int $templateId): bool
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM dirty_job_opportunities opportunity
                LEFT JOIN dirty_job_runs run
                    ON run.opportunity_id = opportunity.id
                    AND run.user_id = opportunity.user_id
                WHERE opportunity.user_id = ?
                  AND opportunity.template_id = ?
                  AND (
                    opportunity.status = 'available'
                    OR run.status IN (
                        'accepted',
                        'preparing',
                        'ready',
                        'executing',
                        'awaiting_decision'
                    )
                  )
            SQL
        );
        $statement->execute([$userId, $templateId]);

        return (int) $statement->fetchColumn() > 0;
    }
}
