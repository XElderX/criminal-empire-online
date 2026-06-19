<?php

namespace App\Services;

final class LocationRiskModifierService
{
    public function forLocation(array $location, ?array $territory = null, ?array $rule = null): array
    {
        $heat = (int) ($location['heat_level'] ?? 0);
        $police = (int) ($location['police_pressure'] ?? 0);
        $danger = (int) ($location['danger_level'] ?? 0);

        $reward = 1.00 + max(-0.20, min(0.25, (($danger - 35) / 250)));
        $heatMultiplier = 1.00 + max(-0.15, min(0.35, (($heat - 25) / 180)));
        $policeMultiplier = 1.00 + max(-0.20, min(0.45, (($police - 30) / 160)));
        $dangerMultiplier = 1.00 + max(-0.15, min(0.45, (($danger - 35) / 160)));

        $territoryEffect = 'Neutral territory uses baseline risk.';
        if ($territory) {
            if ((int) ($territory['government_presence'] ?? 0) >= 70) {
                $policeMultiplier += 0.15;
                $territoryEffect = 'Police-heavy territory increases patrol and arrest pressure.';
            } elseif (!empty($territory['owner_gang'])) {
                $dangerMultiplier += 0.10;
                $territoryEffect = 'Rival-controlled territory increases confrontation risk.';
            } else {
                $heatMultiplier -= 0.05;
                $territoryEffect = 'Neutral territory slightly lowers gang attention.';
            }
        }

        if ($rule) {
            $reward *= (float) ($rule['reward_multiplier'] ?? 1.0);
            $heatMultiplier *= (float) ($rule['heat_multiplier'] ?? 1.0);
            $policeMultiplier *= (float) ($rule['police_risk_multiplier'] ?? 1.0);
            $dangerMultiplier *= (float) ($rule['danger_multiplier'] ?? 1.0);
        }

        return [
            'reward_multiplier' => round(max(0.75, min(1.35, $reward)), 2),
            'heat_multiplier' => round(max(0.75, min(1.50, $heatMultiplier)), 2),
            'police_risk_multiplier' => round(max(0.75, min(1.75, $policeMultiplier)), 2),
            'danger_multiplier' => round(max(0.75, min(1.75, $dangerMultiplier)), 2),
            'territory_effect' => $territoryEffect,
        ];
    }
}
