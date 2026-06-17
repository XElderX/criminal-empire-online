<?php

namespace App\Services;

use App\Config\GameConfig;

final class DirtyJobCalculator
{
    public function __construct(
        private readonly RandomSource $random = new SecureRandomSource()
    ) {
    }

    public function calculate(
        array $template,
        array $user,
        array $district,
        array $assignments,
        array $preparations,
        array $equipment,
        array $decisionEffects = []
    ): array {
        $preparationSummary = $this->summarizePreparations($preparations);
        $equipmentSummary = (new EquipmentEffectService())->aggregate($equipment);

        $crewContribution = $this->crewContribution($assignments);
        $traitModifier = $this->traitModifier($assignments);
        $equipmentModifier = $this->equipmentSuccessModifier(
            (string) $template['category'],
            $equipmentSummary
        );

        $playerContribution = min(8, (int) ($user['intelligence'] ?? 0) / 4);
        $policePenalty = (int) round((int) ($district['police_presence'] ?? 50) / 9);
        $currentHeatPenalty = (int) round((int) ($user['heat'] ?? 0) / 12);

        $rawChance = (int) $template['base_success_rate']
            + $playerContribution
            + $crewContribution
            + $traitModifier
            + $equipmentModifier
            + $preparationSummary['success_bonus']
            + (int) ($decisionEffects['success_bonus'] ?? 0)
            - $policePenalty
            - $currentHeatPenalty
            - (int) ($template['difficulty'] ?? 0) / 8;

        $successChance = max(5, min(95, (int) round($rawChance)));
        $roll = $this->random->integer(1, 100);
        $outcome = $this->determineOutcome($roll, $successChance);

        $baseHeat = $this->random->integer(
            (int) $template['heat_min'],
            (int) $template['heat_max']
        );

        $heat = $baseHeat
            + (int) round((int) ($district['police_presence'] ?? 50) / 25)
            + (int) round((float) ($equipmentSummary['heat_modifier'] ?? 0))
            + $preparationSummary['heat_modifier']
            + (int) ($decisionEffects['heat_modifier'] ?? 0);

        $heat = max(0, $this->applyOutcomeHeatModifier($heat, $outcome));

        $rewardModifier = $preparationSummary['reward_modifier']
            * (float) ($decisionEffects['reward_modifier'] ?? 1.0)
            * (float) (1 + (($equipmentSummary['reward_modifier'] ?? 0) / 1));

        $injuryModifier = $preparationSummary['injury_modifier']
            + (int) round((float) ($equipmentSummary['injury_modifier'] ?? 0));

        return [
            'success_chance' => $successChance,
            'roll' => $roll,
            'outcome' => $outcome,
            'heat' => $heat,
            'reward_modifier' => max(0.25, min(2.0, $rewardModifier)),
            'injury_modifier' => max(-30, min(30, $injuryModifier)),
            'equipment_summary' => $equipmentSummary,
            'preparation_summary' => $preparationSummary,
        ];
    }

    private function crewContribution(array $assignments): int
    {
        if ($assignments === []) {
            return 0;
        }

        $roleDefinitions = GameConfig::crewRoleDefinitions();
        $contribution = 0.0;

        foreach ($assignments as $assignment) {
            $roleCode = (string) ($assignment['role_code'] ?? 'thief');
            $member = $assignment['member'] ?? $assignment;
            $definition = $roleDefinitions[$roleCode] ?? null;
            $stats = $definition['stats'] ?? ['discipline', 'intelligence'];

            $score = 0;

            foreach ($stats as $stat) {
                $score += (int) ($member[$stat] ?? 0);
            }

            $average = $score / max(1, count($stats));
            $healthFactor = min(1.0, (int) ($member['health'] ?? 100) / 100);
            $moraleFactor = 0.75 + min(0.25, (int) ($member['morale'] ?? 50) / 400);

            $contribution += (($average - 35) / 5) * $healthFactor * $moraleFactor;
        }

        return (int) round(max(-10, min(28, $contribution)));
    }

    private function traitModifier(array $assignments): int
    {
        $modifier = 0.0;

        foreach ($assignments as $assignment) {
            $effects = $assignment['trait_effects'] ?? [];

            $modifier -= (float) ($effects['success_penalty'] ?? 0);
            $modifier += (float) ($effects['success_bonus'] ?? 0);
            $modifier += (float) ($effects['driving_bonus'] ?? 0) / 4;
            $modifier += (float) ($effects['street_knowledge_bonus'] ?? 0) / 4;
        }

        return (int) round(max(-20, min(20, $modifier)));
    }

    private function equipmentSuccessModifier(
        string $category,
        array $effects
    ): int {
        $modifier = (float) ($effects['planning_bonus'] ?? 0)
            + (float) ($effects['stealth_bonus'] ?? 0)
            + (float) ($effects['armed_role_bonus'] ?? 0) / 2;

        if ($category === 'burglary') {
            $modifier += (float) ($effects['burglary_bonus'] ?? 0);
            $modifier += (float) ($effects['forced_entry_bonus'] ?? 0) / 2;
            $modifier += (float) ($effects['stealth_entry_bonus'] ?? 0) / 2;
        }

        if ($category === 'vehicle_crime') {
            $modifier += (float) ($effects['driving_bonus'] ?? 0) / 2;
        }

        if ($category === 'production') {
            $modifier += (float) ($effects['production_bonus'] ?? 0);
        }

        if (in_array($category, ['intimidation', 'robbery', 'collection'], true)) {
            $modifier += (float) ($effects['intimidation_bonus'] ?? 0);
        }

        return (int) round(max(-10, min(24, $modifier)));
    }

    private function summarizePreparations(array $preparations): array
    {
        $summary = [
            'success_bonus' => 0,
            'heat_modifier' => 0,
            'injury_modifier' => 0,
            'reward_modifier' => 1.0,
        ];

        foreach ($preparations as $preparation) {
            $summary['success_bonus'] += (int) ($preparation['success_bonus'] ?? 0);
            $summary['heat_modifier'] += (int) ($preparation['heat_modifier'] ?? 0);
            $summary['injury_modifier'] += (int) ($preparation['injury_modifier'] ?? 0);
            $summary['reward_modifier'] *= (float) ($preparation['reward_modifier'] ?? 1.0);
        }

        $summary['success_bonus'] = min(22, $summary['success_bonus']);
        $summary['heat_modifier'] = max(-8, min(8, $summary['heat_modifier']));
        $summary['injury_modifier'] = max(-20, min(20, $summary['injury_modifier']));
        $summary['reward_modifier'] = max(0.75, min(1.5, $summary['reward_modifier']));

        return $summary;
    }

    private function determineOutcome(int $roll, int $successChance): string
    {
        $criticalSuccessThreshold = max(2, (int) floor($successChance * 0.12));

        if ($roll <= $criticalSuccessThreshold) {
            return 'critical_success';
        }

        if ($roll <= $successChance) {
            return 'success';
        }

        if ($roll <= min(98, $successChance + 15)) {
            return 'partial_success';
        }

        if ($roll >= 98) {
            return 'critical_failure';
        }

        return 'failure';
    }

    private function applyOutcomeHeatModifier(int $heat, string $outcome): int
    {
        return match ($outcome) {
            'critical_success' => max(0, $heat - 2),
            'success' => $heat,
            'partial_success' => $heat + 2,
            'failure' => $heat + 4,
            'critical_failure' => $heat + 8,
            default => $heat,
        };
    }
}
