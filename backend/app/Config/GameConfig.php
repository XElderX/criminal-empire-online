<?php

namespace App\Config;

final class GameConfig
{
    public const VERSION = '0.7.3';
    public const RELEASE_TITLE = 'Criminal Empire Online v0.7.3 — Loadout UX & Carry Inventory Polish';

    public const STARTING_CASH = 500;
    public const STARTING_BANK_CASH = 0;
    public const STARTING_DIRTY_MONEY = 0;
    public const STARTING_REPUTATION = 0;
    public const STARTING_HEAT = 0;
    public const STARTING_ENERGY = 100;
    public const STARTING_MAX_ENERGY = 100;

    public const MAX_GANG_MEMBERS = 12;
    public const SALARY_INTERVAL_DAYS = 7;
    public const TUTORIAL_COMPLETION_REWARD = 60;
    public const TUTORIAL_VERSION = '0.6.5';
    public const TUTORIAL_UPDATE_TRIGGER_VERSION = '0.6.4';
    public const TUTORIAL_KEY_FULL = 'new_player_world_guide';
    public const TUTORIAL_KEY_UPDATE = 'world_systems_update';
    public const DIRTY_JOB_OPPORTUNITY_TARGET = 6;
    public const RECRUITMENT_CANDIDATE_TARGET = 3;
    public const WAREHOUSE_DRUG_UNITS_PER_TEN = 1.0;
    public const WAREHOUSE_DEFAULT_WEAPON_UNITS = 4.0;

    public static function jobDurationMultiplier(): float
    {
        $configuredValue = $_ENV['JOB_DURATION_MULTIPLIER']
            ?? getenv('JOB_DURATION_MULTIPLIER')
            ?: 1;

        return max(0.01, min(10.0, (float) $configuredValue));
    }

    public static function tutorialModules(): array
    {
        return [
            'basics' => [
                'title' => 'Welcome & Basics',
                'description' => 'Learn the boss, money, energy, XP, rank, and first goals.',
            ],
            'world' => [
                'title' => 'World Map',
                'description' => 'Regions and hotspots now control local actions, travel risk, and discovery.',
            ],
            'local_actions' => [
                'title' => 'Local Actions',
                'description' => 'Use local presence for exploration, quick crimes, and starter work.',
            ],
            'crew' => [
                'title' => 'Recruitment & Crew',
                'description' => 'Meet recruits, manage crew heat, assign equipment, and build a small team.',
            ],
            'operations' => [
                'title' => 'Dirty Jobs',
                'description' => 'Prepare structured jobs, assign real NPC crew, and resolve consequences.',
            ],
            'pressure' => [
                'title' => 'Heat, Police & Territory',
                'description' => 'Understand investigations, district risk, territory effects, and heat reduction.',
            ],
            'storage' => [
                'title' => 'Warehouse & Progression',
                'description' => 'Use storage, XP, skills, world processing, and next goals to grow safely.',
            ],
        ];
    }

    public static function tutorialSteps(?string $tutorialKey = null): array
    {
        $key = $tutorialKey ?: self::TUTORIAL_KEY_FULL;

        if ($key === self::TUTORIAL_KEY_UPDATE) {
            return self::worldSystemsUpdateTutorialSteps();
        }

        return self::newPlayerTutorialSteps();
    }

    public static function newPlayerTutorialSteps(): array
    {
        return [
            self::tutorialStep('welcome_riverdale', 'basics', 'Welcome to Riverdale County', 'dashboard', 'Acknowledge the single-player goal: start small, learn the city, recruit NPCs, manage heat, and grow carefully.', 'acknowledge', ['message' => 'Start from the dashboard and learn the new map-driven flow.'], true),
            self::tutorialStep('core_stats', 'basics', 'Understand Core Stats', 'dashboard', 'Review cash, bank cash, energy, heat, XP, level, boss health, and crew count.', 'acknowledge', ['message' => 'Stats explain whether you can work, travel, fight heat, and progress.'], true),
            self::tutorialStep('open_world_map', 'world', 'Open the World Map', 'world map', 'Open the World Map and see that regions and hotspots affect gameplay.', 'view_page', ['page' => 'world map']),
            self::tutorialStep('enter_main_city', 'world', 'Enter Main City', 'world map', 'Open the Main City region map or view a Main City hotspot.', 'view_page', ['page' => 'world map', 'context' => 'main_city']),
            self::tutorialStep('travel_to_starter_hotspot', 'world', 'Travel to a Starter Hotspot', 'world map', 'Travel to a local starter hotspot, preferably Main City / Slums, so local actions unlock.', 'travel_to_location', ['region_slug' => 'main-city', 'location_slug' => 'slums']),
            self::tutorialStep('explore_hotspot', 'local_actions', 'Explore the Hotspot', 'world map', 'Use Explore Area at your current hotspot to discover local rumors, leads, or action context.', 'explore_hotspot', []),
            self::tutorialStep('safe_starter_job', 'local_actions', 'Do a Safe Starter Job', 'jobs', 'Complete one starter street job or low-risk fallback action to earn early money.', 'complete_job', []),
            self::tutorialStep('try_quick_crime', 'local_actions', 'Try a Quick Crime', 'crimes', 'Open Quick Crimes and attempt a low-tier action. Success is not required.', 'complete_quick_crime', []),
            self::tutorialStep('understand_results', 'local_actions', 'Understand Results', 'crimes', 'Review how results can produce cash, XP, items, cooldowns, heat, partial success, or failure.', 'acknowledge', ['message' => 'The result screen matters even when an action fails.'], true),
            self::tutorialStep('inspect_recruitment', 'crew', 'Inspect Recruitment', 'recruitment', 'Open Recruitment and inspect a candidate source, cost, stats, traits, salary, and local origin.', 'inspect_candidate', []),
            self::tutorialStep('hire_first_crew', 'crew', 'Hire First Crew Member', 'recruitment', 'Hire one affordable active non-boss NPC crew member when ready.', 'hire_crew', []),
            self::tutorialStep('equip_basic_item', 'crew', 'Equip Basic Gear', 'equipment', 'Equip one basic item to the boss or a crew member.', 'equip_item', [], false, ['cash' => 20]),
            self::tutorialStep('inspect_dirty_job', 'operations', 'Inspect a Dirty Job', 'dirty jobs', 'Open a Dirty Job and review source location, target location, crew requirements, and preparation.', 'inspect_dirty_job', []),
            self::tutorialStep('execute_beginner_dirty_job', 'operations', 'Prepare or Execute a Beginner Dirty Job', 'dirty jobs', 'Take part in a beginner Dirty Job. Completion can be success, partial success, or failure.', 'execute_dirty_job', []),
            self::tutorialStep('view_heat_police', 'pressure', 'Open Heat & Police', 'heat', 'Open Heat & Police and review boss heat, crew heat, gang heat, investigations, and reduction options.', 'view_heat_page', []),
            self::tutorialStep('learn_heat_reduction', 'pressure', 'Learn Heat Reduction', 'heat', 'View heat reduction options such as lie low, bribes, lawyers, and sending crew away.', 'acknowledge', ['message' => 'You do not need to spend money to complete this lesson.'], true),
            self::tutorialStep('view_territory', 'pressure', 'View Territory Risk', 'territories', 'Open Territories or a map-linked territory and review local police, rival, and reward effects.', 'view_territory', []),
            self::tutorialStep('view_warehouse', 'storage', 'Understand Warehouse & Storage', 'warehouse', 'Open Warehouse or warehouse help and learn that physical loot can be stored instead of instantly sold.', 'view_warehouse', []),
            self::tutorialStep('boss_and_succession', 'storage', 'Boss and Succession', 'crew', 'Review that the boss has health, heat, XP, rank, and succession risk if severe events happen.', 'acknowledge', ['message' => 'Keep the boss alive and build eligible crew for the future.'], true),
            self::tutorialStep('finish_world_tutorial', 'storage', 'Finish the World Tutorial', 'guide', 'Review next goals: earn money, hire crew, travel to map shops for equipment, reduce heat, explore hotspots, and save for storage.', 'view_guide', [], true, ['cash' => self::TUTORIAL_COMPLETION_REWARD, 'xp' => 15]),
        ];
    }

    public static function worldSystemsUpdateTutorialSteps(): array
    {
        return [
            self::tutorialStep('update_open_world_map', 'world', 'Open World Map', 'world map', 'Review the world map added in recent updates.', 'view_page', ['page' => 'world map']),
            self::tutorialStep('update_travel_hotspot', 'world', 'Travel to a Hotspot', 'world map', 'Travel to any hotspot to update current local presence.', 'travel_to_location', []),
            self::tutorialStep('update_view_local_actions', 'local_actions', 'View Local Actions', 'world map', 'Open a hotspot panel and review which actions are available here or require travel.', 'view_page', ['page' => 'world map', 'context' => 'local_actions']),
            self::tutorialStep('update_explore_hotspot', 'local_actions', 'Explore a Hotspot', 'world map', 'Use hotspot exploration once to see how local rumors and leads can appear.', 'explore_hotspot', []),
            self::tutorialStep('update_heat_police', 'pressure', 'Open Heat & Police', 'heat', 'Review boss heat, crew heat, gang heat, and police investigations.', 'view_heat_page', []),
            self::tutorialStep('update_territory_effects', 'pressure', 'View Local / Territory Effects', 'territories', 'Review how territory and district risk affect local gameplay.', 'view_territory', []),
            self::tutorialStep('update_finish', 'storage', 'Finish World Systems Update', 'guide', 'Open the guide once; your older tutorial progress stays intact.', 'view_guide', [], true, ['cash' => 20, 'xp' => 5]),
        ];
    }

    public static function contextualHelpTips(): array
    {
        return [
            'dashboard' => ['page' => 'dashboard', 'title' => 'Dashboard', 'body' => 'Use the dashboard to check boss status, current location, heat, energy, XP, and the next safe goal.'],
            'world_map' => ['page' => 'world map', 'title' => 'World Map', 'body' => 'Regions and hotspots are not just art. Travel changes what local actions, risks, contacts, and events are available.'],
            'location_map' => ['page' => 'world map', 'title' => 'Location Map', 'body' => 'A hotspot can be viewed remotely, but local actions often require being physically present there.'],
            'crimes' => ['page' => 'crimes', 'title' => 'Crimes & Quick Crimes', 'body' => 'Quick Crimes are fast actions. Some are local and require travel; risk changes with heat, location, and equipment.'],
            'dirty_jobs' => ['page' => 'dirty jobs', 'title' => 'Dirty Jobs', 'body' => 'Dirty Jobs are structured operations with preparation, crew assignment, equipment, travel requirements, and consequences.'],
            'recruitment' => ['page' => 'recruitment', 'title' => 'Recruitment', 'body' => 'Recruitable NPCs have stats, traits, salary, morale, loyalty, heat, and sometimes local source hotspots.'],
            'crew' => ['page' => 'crew', 'title' => 'Crew', 'body' => 'Crew members are persistent NPCs. Watch their heat, injuries, status, equipment, history, and loyalty.'],
            'equipment' => ['page' => 'equipment', 'title' => 'Inventory & Equipment', 'body' => 'Inventory now focuses on owned-item management and crew loadouts. Buy gear from map shops after traveling there.'],
            'shops' => ['page' => 'shops', 'title' => 'Map Shops', 'body' => 'Shops and dealers live on map hotspots. Travel to a shop to buy or sell; powerful items can be disabled or black-market-only by config.'],
            'warehouse' => ['page' => 'warehouse', 'title' => 'Warehouse', 'body' => 'Storage helps keep physical loot and illegal goods off the boss while adding capacity and security choices.'],
            'territories' => ['page' => 'territories', 'title' => 'Territories', 'body' => 'Territories connect to map areas. Police pressure and rival control can change local danger and rewards.'],
            'heat' => ['page' => 'heat', 'title' => 'Heat & Police', 'body' => 'Heat belongs to the boss, crew, gang, NPCs, and districts. High heat can feed investigations and travel risk.'],
            'market' => ['page' => 'market', 'title' => 'Drug Market', 'body' => 'The drug market is an abstract economy page; prices, supply, demand, and police pressure vary by region.'],
            'jobs' => ['page' => 'jobs', 'title' => 'Street Jobs', 'body' => 'Street Jobs are starter work but still require at least one active real NPC crew member after v0.6.3.1.'],
        ];
    }

    public static function guideSections(): array
    {
        return [
            ['key' => 'beginner_path', 'title' => 'Beginner Path', 'body' => 'Start from the dashboard, open the world map, travel to a starter hotspot, explore, do small work, hire one crew member, equip basic gear, then inspect safer Dirty Jobs.'],
            ['key' => 'world_map', 'title' => 'World Map & Hotspots', 'body' => 'Hotspots define local quick crimes, dirty job leads, contacts, business hooks, police pressure, and territory context. Viewing is remote; acting often requires local presence.'],
            ['key' => 'travel', 'title' => 'Travel & Local Presence', 'body' => 'Travel costs small energy or cash, may trigger events, records history, and unlocks local actions. High heat or illegal carried goods increase travel risk.'],
            ['key' => 'quick_crimes', 'title' => 'Quick Crimes', 'body' => 'Quick Crimes are fast, low-to-mid tier actions. Some are location-specific and will tell you where to travel before starting.'],
            ['key' => 'dirty_jobs', 'title' => 'Dirty Jobs', 'body' => 'Dirty Jobs have contacts, preparation, crew roles, equipment, execution, and consequences. Some require local presence before accepting or executing.'],
            ['key' => 'crew', 'title' => 'Crew & Recruitment', 'body' => 'Crew are named NPCs with portraits, age, stats, traits, salaries, heat, morale, loyalty, and histories. Low-level dismissed crew may return to recruitment; experienced crew return to ordinary NPC life.'],
            ['key' => 'equipment', 'title' => 'Equipment', 'body' => 'Owned items can be equipped to the boss or crew from Inventory. One item cannot be equipped by multiple crew at the same time.'],
            ['key' => 'shops', 'title' => 'Map Shops & Item Availability', 'body' => 'Buying moved into the world. Travel to tool shops, workwear stores, garages, medical counters, pawn fences, or black-market contacts to buy and sell. Shop config controls which items are enabled, restricted, black-market-only, or future-only.'],
            ['key' => 'heat_police', 'title' => 'Heat & Police Pressure', 'body' => 'Actions and travel can raise heat. Heat can exist on the boss, crew, gang, districts, and NPCs. Reduction options and quiet days help manage pressure.'],
            ['key' => 'territories', 'title' => 'Territories', 'body' => 'Territories tie map places to risk, rewards, police presence, and rival pressure. Scout before pushing deeper.'],
            ['key' => 'warehouse', 'title' => 'Warehouse & Storage', 'body' => 'Warehouses store physical loot and supplies. Stored items can reduce travel-carrying risk where systems support it.'],
            ['key' => 'progression', 'title' => 'XP, Skills & World Processing', 'body' => 'XP and skills grow through actions. Hourly/daily/weekly world processing refreshes jobs, recruitment, recovery, heat decay, salaries, and map opportunities.'],
            ['key' => 'boss_succession', 'title' => 'Boss & Succession', 'body' => 'The boss is a character with health, rank, XP, and heat. Severe outcomes can injure, arrest, or eventually kill the boss; eligible crew can become successors.'],
        ];
    }

    private static function tutorialStep(
        string $code,
        string $moduleKey,
        string $title,
        string $page,
        string $objective,
        string $objectiveType,
        array $objectivePayload = [],
        bool $requiresAcknowledgement = false,
        array $rewardPayload = []
    ): array {
        return [
            'code' => $code,
            'step_key' => $code,
            'module_key' => $moduleKey,
            'module_title' => self::tutorialModules()[$moduleKey]['title'] ?? $moduleKey,
            'title' => $title,
            'page' => $page,
            'route_hint' => $page,
            'objective' => $objective,
            'objective_type' => $objectiveType,
            'objective_payload' => $objectivePayload,
            'requires_acknowledgement' => $requiresAcknowledgement,
            'reward_payload' => $rewardPayload,
            'is_optional' => false,
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
