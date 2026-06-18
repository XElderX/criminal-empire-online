<?php

namespace App\Services;

final class CrimeNarrativeService
{
    /** @return array<int, array<string, mixed>> */
    public function preparationOptions(): array
    {
        return [
            [
                'code' => 'scout_target',
                'name' => 'Scout target',
                'description' => 'Spend time observing the target abstractly to improve the risk estimate.',
                'cash_cost' => 0,
                'energy_cost' => 3,
                'effects' => ['success' => 6, 'police' => -1, 'witness' => -2],
            ],
            [
                'code' => 'buy_information',
                'name' => 'Buy information',
                'description' => 'Pay the source for better details. Low-trust contacts can still exaggerate.',
                'cash_cost' => 80,
                'energy_cost' => 1,
                'effects' => ['success' => 5, 'disaster' => -2],
            ],
            [
                'code' => 'secure_escape_route',
                'name' => 'Secure escape route',
                'description' => 'Plan a safer exit path in broad game terms.',
                'cash_cost' => 60,
                'energy_cost' => 2,
                'effects' => ['success' => 3, 'police' => -4, 'disaster' => -2],
            ],
            [
                'code' => 'arrange_fence',
                'name' => 'Arrange fence',
                'description' => 'Find a buyer before the run to improve payout reliability.',
                'cash_cost' => 120,
                'energy_cost' => 1,
                'effects' => ['loot' => 8, 'success' => 2],
            ],
            [
                'code' => 'check_patrols',
                'name' => 'Check patrol pressure',
                'description' => 'Ask around about visible patrol pressure without exposing exact tactics.',
                'cash_cost' => 40,
                'energy_cost' => 2,
                'effects' => ['police' => -5, 'witness' => -1],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function event(string $code): array
    {
        $events = [
            'police_patrol' => [
                'code' => 'police_patrol',
                'title' => 'Police patrol nearby',
                'description' => 'A patrol rolls through the area during the escape window. The crew needs a quick decision.',
                'choices' => [
                    ['code' => 'abandon_loot', 'label' => 'Abandon part of the loot', 'description' => 'Lower reward, lower heat.'],
                    ['code' => 'hide_wait', 'label' => 'Hide and wait', 'description' => 'Safer but increases witness risk.'],
                    ['code' => 'push_through', 'label' => 'Push through quickly', 'description' => 'Higher reward chance but more heat.'],
                ],
            ],
            'witness_spotted' => [
                'code' => 'witness_spotted',
                'title' => 'Witness noticed movement',
                'description' => 'Someone may have seen enough to describe the crew later.',
                'choices' => [
                    ['code' => 'leave_now', 'label' => 'Leave immediately', 'description' => 'End fast with lower reward.'],
                    ['code' => 'bribe_witness', 'label' => 'Offer quiet money', 'description' => 'Costs cash but may reduce later suspicion.'],
                    ['code' => 'ignore_witness', 'label' => 'Ignore it', 'description' => 'Keep moving but accept more heat.'],
                ],
            ],
            'rival_interference' => [
                'code' => 'rival_interference',
                'title' => 'Rival crew appears',
                'description' => 'A rival NPC crew is also watching the same opportunity.',
                'choices' => [
                    ['code' => 'split_score', 'label' => 'Split the score', 'description' => 'Lower reward but less danger.'],
                    ['code' => 'retreat', 'label' => 'Retreat', 'description' => 'Avoid the clash and preserve the crew.'],
                    ['code' => 'stand_ground', 'label' => 'Stand ground', 'description' => 'Higher risk and reputation swing.'],
                ],
            ],
            'equipment_failure' => [
                'code' => 'equipment_failure',
                'title' => 'Equipment problem',
                'description' => 'One item does not perform as expected and slows the run.',
                'choices' => [
                    ['code' => 'continue_penalty', 'label' => 'Continue with penalty', 'description' => 'Continue with lower success chance.'],
                    ['code' => 'abort', 'label' => 'Abort', 'description' => 'End now with low heat.'],
                    ['code' => 'use_backup', 'label' => 'Use backup tool', 'description' => 'Requires useful backup equipment.'],
                ],
            ],
            'extra_loot' => [
                'code' => 'extra_loot',
                'title' => 'Extra loot discovered',
                'description' => 'The crew finds more than expected, but staying longer will raise the risk.',
                'choices' => [
                    ['code' => 'take_extra', 'label' => 'Take extra loot', 'description' => 'More reward, more heat and risk.'],
                    ['code' => 'leave_extra', 'label' => 'Leave it', 'description' => 'Keep the plan controlled.'],
                    ['code' => 'assign_carry', 'label' => 'Assign more crew to carry', 'description' => 'Better if the run has enough crew.'],
                ],
            ],
            'buyer_refuses' => [
                'code' => 'buyer_refuses',
                'title' => 'Buyer hesitates',
                'description' => 'The buyer gets nervous because city heat is rising.',
                'choices' => [
                    ['code' => 'accept_discount', 'label' => 'Accept lower payout', 'description' => 'Finish safely with reduced reward.'],
                    ['code' => 'hold_goods', 'label' => 'Hold goods', 'description' => 'Delay reward and raise warehouse risk later.'],
                    ['code' => 'pressure_buyer', 'label' => 'Pressure buyer', 'description' => 'May keep reward but increases reputation risk.'],
                ],
            ],
        ];

        return $events[$code] ?? $events['witness_spotted'];
    }

    /** @return array<string, string> */
    public function outcomeText(string $outcome): array
    {
        return match ($outcome) {
            'critical_success' => [
                'title' => 'Clean score',
                'description' => 'The crew kept the plan controlled and found more value than expected.',
            ],
            'success' => [
                'title' => 'Crime completed',
                'description' => 'The opportunity paid out and the crew left before pressure built too high.',
            ],
            'partial_success' => [
                'title' => 'Partial success',
                'description' => 'The crew escaped with something, but the run was messier than planned.',
            ],
            'failed_escaped' => [
                'title' => 'Failed but escaped',
                'description' => 'The crew got away, but the target did not pay off.',
            ],
            'failed_injury' => [
                'title' => 'Injury during escape',
                'description' => 'The run failed and one crew member was hurt in the chaos.',
            ],
            'failed_arrest' => [
                'title' => 'Arrest consequence',
                'description' => 'The run failed and one crew member was taken in by police.',
            ],
            'police_trap' => [
                'title' => 'Bad information',
                'description' => 'The source information was dangerous and police pressure spiked.',
            ],
            'abandoned' => [
                'title' => 'Abandoned',
                'description' => 'The crew walked away before it got worse.',
            ],
            default => [
                'title' => 'Crime resolved',
                'description' => 'The run reached an outcome and the city state was updated.',
            ],
        };
    }
}
