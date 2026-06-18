<?php

namespace App\Services;

final class CrimeRiskCalculator
{
    /**
     * @param array<string, mixed> $template
     * @param array<int, array<string, mixed>> $crew
     * @param array<int, array<string, mixed>> $equipment
     * @param array<int, array<string, mixed>> $preparations
     * @param array<string, mixed> $context
     * @return array<string, int>
     */
    public function calculate(
        array $template,
        array $crew,
        array $equipment,
        array $preparations,
        array $context = []
    ): array {
        $successChance = (int) ($template['base_success_rate'] ?? 50);
        $disasterChance = (int) ($template['base_disaster_chance'] ?? 5);
        $policeChance = 8;
        $witnessRisk = 12;
        $lootModifier = 0;

        $stats = $this->crewStatAverage($crew, $this->jsonList($template['relevant_stats'] ?? []));
        if ($stats > 0) {
            $successChance += (int) floor(($stats - 45) / 4);
            $disasterChance -= (int) floor(max(0, $stats - 50) / 12);
        }

        $crewCount = count($crew);
        $minimumCrew = (int) ($template['min_crew'] ?? 0);
        $maximumCrew = max(1, (int) ($template['max_crew'] ?? 3));

        if ($crewCount < $minimumCrew) {
            $successChance -= 18 * ($minimumCrew - $crewCount);
            $disasterChance += 8 * ($minimumCrew - $crewCount);
        }

        if ($crewCount > $maximumCrew) {
            $successChance -= 5 * ($crewCount - $maximumCrew);
            $policeChance += 4 * ($crewCount - $maximumCrew);
            $witnessRisk += 3 * ($crewCount - $maximumCrew);
        }

        foreach ($equipment as $entry) {
            $effects = $this->jsonMap($entry['effects'] ?? []);
            $successChance += (int) floor((float) ($effects['stealth'] ?? 0));
            $successChance += (int) floor((float) ($effects['scouting'] ?? 0));
            $successChance += (int) floor((float) ($effects['driving'] ?? 0));
            $successChance += (int) floor((float) ($effects['vehicle_escape'] ?? 0));
            $lootModifier += (int) floor((float) ($effects['loot_capacity'] ?? 0));
            $witnessRisk += (int) floor((float) ($effects['witness_risk'] ?? 0));
            $policeChance += (int) floor((float) ($effects['police_risk'] ?? 0));
            $disasterChance -= (int) floor((float) ($effects['injury_reduction'] ?? 0) / 3);
        }

        foreach ($preparations as $preparation) {
            $effects = $this->jsonMap($preparation['effects'] ?? []);
            $successChance += (int) floor((float) ($effects['success'] ?? 0));
            $successChance += (int) floor((float) ($effects['stealth'] ?? 0));
            $lootModifier += (int) floor((float) ($effects['loot'] ?? 0));
            $policeChance += (int) floor((float) ($effects['police'] ?? 0));
            $witnessRisk += (int) floor((float) ($effects['witness'] ?? 0));
            $disasterChance += (int) floor((float) ($effects['disaster'] ?? 0));
        }

        $heat = (int) ($context['heat'] ?? 0);
        $districtPolice = (int) ($context['district_police_presence'] ?? 40);
        $contactReliability = (int) ($context['contact_reliability'] ?? 50);
        $quality = (string) ($context['quality'] ?? 'normal');

        $policeChance += (int) floor($heat / 12) + (int) floor(max(0, $districtPolice - 45) / 10);
        $disasterChance += (int) floor(max(0, $heat - 60) / 15);

        if ($contactReliability < 35) {
            $successChance -= 8;
            $disasterChance += 5;
        } elseif ($contactReliability > 70) {
            $successChance += 4;
            $disasterChance -= 2;
        }

        if ($quality === 'strong') {
            $successChance += 8;
            $policeChance -= 2;
        } elseif ($quality === 'weak') {
            $successChance -= 5;
        } elseif ($quality === 'suspicious') {
            $successChance -= 8;
            $disasterChance += 8;
        } elseif ($quality === 'trap') {
            $successChance -= 18;
            $disasterChance += 15;
            $policeChance += 15;
        }

        return [
            'success_chance' => $this->clamp($successChance, 5, 92),
            'disaster_chance' => $this->clamp($disasterChance, 1, 45),
            'police_chance' => $this->clamp($policeChance, 2, 70),
            'witness_risk' => $this->clamp($witnessRisk, 1, 80),
            'loot_modifier' => $this->clamp($lootModifier, -20, 40),
        ];
    }

    /** @param array<int, array<string, mixed>> $crew */
    private function crewStatAverage(array $crew, array $statNames): int
    {
        if ($crew === [] || $statNames === []) {
            return 0;
        }

        $total = 0;
        $count = 0;

        foreach ($crew as $member) {
            foreach ($statNames as $statName) {
                if (isset($member[$statName]) && is_numeric($member[$statName])) {
                    $total += (int) $member[$statName];
                    $count++;
                }
            }
        }

        return $count > 0 ? (int) round($total / $count) : 0;
    }

    private function clamp(int $value, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, $value));
    }

    /** @return array<int, string> */
    private function jsonList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @return array<string, mixed> */
    private function jsonMap(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
