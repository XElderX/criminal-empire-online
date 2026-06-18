<?php

namespace App\Services;

use App\Config\GameConfig;

final class CrewPresentationService
{
    /**
     * @param array<string, mixed> $person
     * @return array<string, mixed>
     */
    public function present(array $person): array
    {
        $roleCode = $this->resolveRoleCode($person);
        $roleDefinition = GameConfig::crewRoleDefinitions()[$roleCode]
            ?? GameConfig::crewRoleDefinitions()['thief'];
        $experience = (int) ($person['experience'] ?? 0);
        $level = max(1, (int) ($person['level'] ?? 1));
        $experienceForNextLevel = max(500, $level * 500);
        $experienceIntoLevel = $experience % $experienceForNextLevel;
        $progressPercent = min(
            100,
            (int) round(($experienceIntoLevel / $experienceForNextLevel) * 100)
        );

        return [
            ...$person,
            'portrait' => (new CrewPortraitResolver())->resolve($person),
            'life_stage' => (new CrewAgeStageResolver())->resolve(
                (int) ($person['age'] ?? 0)
            ),
            'role_code' => $roleCode,
            'role' => [
                'key' => $roleCode,
                'name' => $roleDefinition['name'],
                'description' => $roleDefinition['description'],
                'stats' => $roleDefinition['stats'],
                'accent' => $roleDefinition['accent'] ?? 'neutral',
                'icon' => $roleDefinition['icon'] ?? '◆',
            ],
            'experience_for_next_level' => $experienceForNextLevel,
            'experience_into_level' => $experienceIntoLevel,
            'experience_progress_percent' => $progressPercent,
            'reputation_label' => $this->reputationLabel(
                (int) ($person['criminal_reputation'] ?? $person['reputation'] ?? 0)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $person
     */
    private function resolveRoleCode(array $person): string
    {
        $occupation = strtolower((string) ($person['occupation'] ?? ''));

        if (str_contains($occupation, 'medic') || str_contains($occupation, 'nurse')) {
            return 'medic';
        }

        $scores = [
            'driver' => $this->average($person, ['driving', 'discipline', 'endurance']),
            'lookout' => $this->average(
                $person,
                ['street_knowledge', 'intelligence', 'discipline']
            ),
            'enforcer' => $this->average(
                $person,
                ['strength', 'intimidation', 'shooting']
            ),
            'infiltrator' => $this->average(
                $person,
                ['stealth', 'intelligence', 'discipline']
            ),
            'planner' => $this->average(
                $person,
                ['intelligence', 'street_knowledge', 'discipline']
            ),
            'courier' => $this->average(
                $person,
                ['driving', 'street_knowledge', 'endurance']
            ),
            'thief' => $this->average(
                $person,
                ['stealth', 'street_knowledge', 'discipline']
            ),
        ];

        arsort($scores);

        return (string) array_key_first($scores);
    }

    /**
     * @param array<string, mixed> $person
     * @param array<int, string> $stats
     */
    private function average(array $person, array $stats): float
    {
        $values = array_map(
            static fn (string $stat): int => (int) ($person[$stat] ?? 0),
            $stats
        );

        return array_sum($values) / max(1, count($values));
    }

    private function reputationLabel(int $reputation): string
    {
        return match (true) {
            $reputation >= 80 => 'Feared',
            $reputation >= 55 => 'Respected',
            $reputation >= 30 => 'Known',
            $reputation >= 10 => 'Recognized',
            default => 'Unknown',
        };
    }
}
