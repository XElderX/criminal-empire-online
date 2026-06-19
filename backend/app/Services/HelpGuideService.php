<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;

final class HelpGuideService
{
    public function guide(): array
    {
        $sections = $this->storedSections();

        if ($sections === []) {
            $sections = GameConfig::guideSections();
        }

        return [
            'title' => 'Criminal Empire Online Guide',
            'version' => GameConfig::VERSION,
            'release_title' => GameConfig::RELEASE_TITLE,
            'sections' => array_values(array_map(
                static fn (array $section): array => [
                    'key' => (string) ($section['section_key'] ?? $section['key']),
                    'title' => (string) $section['title'],
                    'body' => (string) $section['body'],
                ],
                $sections
            )),
        ];
    }

    private function storedSections(): array
    {
        $statement = Database::pdo()->query(
            'SELECT * FROM guide_sections WHERE is_active = 1 ORDER BY sort_order, id'
        );

        return $statement->fetchAll();
    }
}
