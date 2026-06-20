<?php

namespace App\Services;

final class LoadoutScoreService
{
    public function score(array $equippedItems, array $carriedItems = []): array
    {
        $score = [
            'stealth' => 50,
            'intimidation' => 10,
            'protection' => 0,
            'carry_capacity' => 5,
            'police_suspicion' => 0,
            'mobility' => 75,
            'evidence_safety' => 0,
            'utility' => 0,
        ];

        foreach (array_merge($equippedItems, $carriedItems) as $item) {
            $effects = (new ItemEffectService())->effectsForItem($item);
            $score['stealth'] += (int) ($effects['stealth_bonus'] ?? 0);
            $score['intimidation'] += (int) ($effects['intimidation_bonus'] ?? 0);
            $score['protection'] += (int) ($effects['injury_reduction'] ?? 0);
            $score['carry_capacity'] += (int) ($effects['carry_capacity_bonus'] ?? 0);
            $score['police_suspicion'] += (int) ($effects['police_suspicion_bonus'] ?? 0);
            $score['mobility'] -= (int) ($effects['mobility_penalty'] ?? 0);
            $score['evidence_safety'] += (int) ((1.0 - (float) ($effects['evidence_risk_multiplier'] ?? 1.0)) * 100);
            $score['utility'] += (int) ($effects['utility'] ?? $effects['forced_entry_bonus'] ?? $effects['vehicle_crime_bonus'] ?? 0);
            if ((int) ($item['visible_illegal'] ?? 0) === 1) {
                $score['police_suspicion'] += 8;
            }
        }

        foreach ($score as $key => $value) {
            $score[$key] = max(0, min(100, $value));
        }

        return $score;
    }
}
