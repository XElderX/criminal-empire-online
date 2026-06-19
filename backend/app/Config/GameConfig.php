<?php

namespace App\Config;

final class GameConfig
{
    public const VERSION = '0.5.1.2';
    public const RELEASE_TITLE = 'Criminal Empire Online v0.5.1.2 — Admin Heat Reset';

    public const STARTING_CASH = 500;
    public const STARTING_BANK_CASH = 0;
    public const STARTING_DIRTY_MONEY = 0;
    public const STARTING_REPUTATION = 0;
    public const STARTING_HEAT = 0;
    public const STARTING_ENERGY = 100;
    public const STARTING_MAX_ENERGY = 100;

    public const MAX_GANG_MEMBERS = 12;
    public const SALARY_INTERVAL_DAYS = 7;
    public const TUTORIAL_COMPLETION_REWARD = 50;
    public const DIRTY_JOB_OPPORTUNITY_TARGET = 6;
    public const WAREHOUSE_DRUG_UNITS_PER_TEN = 1.0;
    public const WAREHOUSE_DEFAULT_WEAPON_UNITS = 4.0;

    public static function jobDurationMultiplier(): float
    {
        $configuredValue = $_ENV['JOB_DURATION_MULTIPLIER']
            ?? getenv('JOB_DURATION_MULTIPLIER')
            ?: 1;

        return max(0.01, min(10.0, (float) $configuredValue));
    }

    public static function tutorialSteps(): array
    {
        return [
            [
                'code' => 'welcome',
                'title' => 'Welcome to the City',
                'page' => 'dashboard',
                'objective' => 'Review cash, energy, heat, level, experience, and reputation.',
                'requires_acknowledgement' => true,
            ],
            [
                'code' => 'first_money',
                'title' => 'Earn Your First Money',
                'page' => 'jobs',
                'objective' => 'Complete one safe legal starter job.',
                'requires_acknowledgement' => false,
            ],
            [
                'code' => 'first_illegal_job',
                'title' => 'Your First Illegal Job',
                'page' => 'jobs',
                'objective' => 'Attempt one criminal starter job or crime.',
                'requires_acknowledgement' => false,
            ],
            [
                'code' => 'first_recruit',
                'title' => 'Recruit Your First Crew Member',
                'page' => 'recruitment',
                'objective' => 'Hire an affordable street-level recruit.',
                'requires_acknowledgement' => false,
            ],
            [
                'code' => 'crew_overview',
                'title' => 'Understand Your Crew',
                'page' => 'crew',
                'objective' => 'Review a member’s stats, traits, health, morale, loyalty, salary, and history.',
                'requires_acknowledgement' => true,
            ],
            [
                'code' => 'basic_equipment',
                'title' => 'Equip Basic Gear',
                'page' => 'equipment',
                'objective' => 'Buy an item and equip it to a crew member.',
                'requires_acknowledgement' => false,
            ],
            [
                'code' => 'prepare_dirty_job',
                'title' => 'Prepare a Dirty Job',
                'page' => 'dirty jobs',
                'objective' => 'Accept a Dirty Job and complete at least one preparation action.',
                'requires_acknowledgement' => false,
            ],
            [
                'code' => 'execute_dirty_job',
                'title' => 'Execute the Operation',
                'page' => 'dirty jobs',
                'objective' => 'Execute and resolve a Dirty Job. Success is not required.',
                'requires_acknowledgement' => false,
            ],
            [
                'code' => 'heat_consequences',
                'title' => 'Heat and Consequences',
                'page' => 'dashboard',
                'objective' => 'Review how heat, evidence, injuries, and arrests affect future work.',
                'requires_acknowledgement' => true,
            ],
            [
                'code' => 'warehouse_intro',
                'title' => 'The Warehouse',
                'page' => 'warehouse',
                'objective' => 'Review warehouse storage, security, operating costs, and future progression.',
                'requires_acknowledgement' => true,
            ],
        ];
    }

    public static function crewRoleDefinitions(): array
    {
        return [
            'leader' => [
                'name' => 'Leader',
                'description' => 'Keeps the crew coordinated when plans change.',
                'stats' => ['intelligence', 'discipline', 'loyalty'],
                'accent' => 'red',
                'icon' => '◆',
            ],
            'driver' => [
                'name' => 'Driver',
                'description' => 'Handles routes, escapes, and vehicle control.',
                'stats' => ['driving', 'discipline', 'endurance'],
                'accent' => 'amber',
                'icon' => '◉',
            ],
            'lookout' => [
                'name' => 'Lookout',
                'description' => 'Watches patrols, witnesses, and unexpected movement.',
                'stats' => ['street_knowledge', 'intelligence', 'discipline'],
                'accent' => 'violet',
                'icon' => '◎',
            ],
            'enforcer' => [
                'name' => 'Enforcer',
                'description' => 'Applies controlled pressure during collection and robbery work.',
                'stats' => ['strength', 'intimidation', 'shooting'],
                'accent' => 'orange',
                'icon' => '✦',
            ],
            'thief' => [
                'name' => 'Thief',
                'description' => 'Handles quick theft and low-profile movement.',
                'stats' => ['stealth', 'street_knowledge', 'discipline'],
                'accent' => 'purple',
                'icon' => '◇',
            ],
            'infiltrator' => [
                'name' => 'Infiltrator',
                'description' => 'Deals with quiet entry, locks, and secured interiors.',
                'stats' => ['stealth', 'intelligence', 'discipline'],
                'accent' => 'violet',
                'icon' => '◈',
            ],
            'planner' => [
                'name' => 'Planner',
                'description' => 'Turns information and preparation into a safer operation.',
                'stats' => ['intelligence', 'street_knowledge', 'discipline'],
                'accent' => 'blue',
                'icon' => '▣',
            ],
            'weapons_specialist' => [
                'name' => 'Weapons Specialist',
                'description' => 'Uses armed equipment without losing control of the operation.',
                'stats' => ['shooting', 'discipline', 'endurance'],
                'accent' => 'red',
                'icon' => '⌖',
            ],
            'courier' => [
                'name' => 'Courier',
                'description' => 'Moves goods through the city and protects delivery timing.',
                'stats' => ['driving', 'street_knowledge', 'endurance'],
                'accent' => 'amber',
                'icon' => '➤',
            ],
            'grow_operator' => [
                'name' => 'Grow Operator',
                'description' => 'Runs the fictionalized production cycle as an abstract game task.',
                'stats' => ['intelligence', 'discipline', 'loyalty'],
                'accent' => 'red',
                'icon' => '◆',
            ],
            'warehouse_handler' => [
                'name' => 'Warehouse Handler',
                'description' => 'Manages storage, loading, and secure movement of goods.',
                'stats' => ['strength', 'discipline', 'street_knowledge'],
                'accent' => 'neutral',
                'icon' => '▦',
            ],
            'medic' => [
                'name' => 'Medic',
                'description' => 'Keeps injuries manageable and improves recovery planning.',
                'stats' => ['intelligence', 'discipline', 'endurance'],
                'accent' => 'green',
                'icon' => '✚',
            ],
        ];
    }
}
