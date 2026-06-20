<?php

namespace App\Services;

final class LoadoutPenaltyService
{
    public function warnings(array $scores, float $usedCarryUnits, float $capacity): array
    {
        $warnings = [];
        if ($usedCarryUnits > $capacity) {
            $warnings[] = 'Over carry capacity: stealth and escape chance suffer.';
        }
        if (($scores['police_suspicion'] ?? 0) >= 40) {
            $warnings[] = 'High police suspicion from visible illegal or bulky gear.';
        }
        if (($scores['mobility'] ?? 100) <= 35) {
            $warnings[] = 'Low mobility: heavy gear can slow job execution and escape.';
        }
        return $warnings;
    }
}
