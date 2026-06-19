<?php

namespace App\Services;

final class MapRiskService
{
    public function summarize(array $row, ?array $territory = null): array
    {
        $heat = (int) ($row['heat_level'] ?? $row['base_heat'] ?? 0);
        $police = (int) ($row['police_pressure'] ?? 0);
        $danger = (int) ($row['danger_level'] ?? 0);

        if ($territory) {
            $heat = max($heat, (int) ($territory['district_heat'] ?? 0));
            $police = max($police, (int) ($territory['government_presence'] ?? 0));
            $danger = max($danger, (int) ($territory['crime_rate'] ?? 0));
        }

        $score = (int) round(($heat * 0.35) + ($police * 0.35) + ($danger * 0.30));

        return [
            'heat' => $heat,
            'police_pressure' => $police,
            'danger_level' => $danger,
            'score' => $score,
            'label' => $this->label($score, $police),
            'tone' => $this->tone($score, $police),
        ];
    }

    public function label(int $score, int $police = 0): string
    {
        if ($police >= 75) {
            return 'Police Heavy';
        }

        if ($score >= 75) {
            return 'Hot Zone';
        }

        if ($score >= 60) {
            return 'Dangerous';
        }

        if ($score >= 42) {
            return 'Risky';
        }

        if ($score >= 22) {
            return 'Low Risk';
        }

        return 'Safe';
    }

    public function tone(int $score, int $police = 0): string
    {
        if ($police >= 75 || $score >= 75) {
            return 'heat';
        }

        if ($score >= 60) {
            return 'danger';
        }

        if ($score >= 42) {
            return 'risk';
        }

        if ($score >= 22) {
            return 'warm';
        }

        return 'safe';
    }
}
