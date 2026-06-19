<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/SequenceRandomSource.php';

use App\Config\GameConfig;
use App\Services\DirtyJobCalculator;
use Tests\SequenceRandomSource;
use Tests\TestRunner;

$runner = new TestRunner();

$baseTemplate = [
    'category' => 'burglary',
    'base_success_rate' => 60,
    'difficulty' => 20,
    'heat_min' => 2,
    'heat_max' => 2,
];

$baseUser = [
    'intelligence' => 20,
    'heat' => 0,
];

$baseDistrict = [
    'police_presence' => 45,
];

$runner->test('Version is v0.5.1.3', function () use ($runner): void {
    $runner->assertSame('0.5.1.3', GameConfig::VERSION);
});

$runner->test('Release title identifies dirty job crew hotfix', function () use ($runner): void {
    $runner->assertContains('Dirty Job Crew Requirement Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('New-player starting cash is exactly 500', function () use ($runner): void {
    $runner->assertSame(500, GameConfig::STARTING_CASH);
});

$runner->test('New-player bank and dirty balances start at zero', function () use ($runner): void {
    $runner->assertSame(0, GameConfig::STARTING_BANK_CASH);
    $runner->assertSame(0, GameConfig::STARTING_DIRTY_MONEY);
});

$runner->test('Tutorial contains ten sequential steps', function () use ($runner): void {
    $steps = GameConfig::tutorialSteps();
    $runner->assertSame(10, count($steps));
    $runner->assertSame('welcome', $steps[0]['code']);
    $runner->assertSame('warehouse_intro', $steps[9]['code']);
});

$runner->test('Tutorial step codes are unique', function () use ($runner): void {
    $codes = array_column(GameConfig::tutorialSteps(), 'code');
    $runner->assertSame(count($codes), count(array_unique($codes)));
});

$runner->test('Crew role definitions are structured and readable', function () use ($runner): void {
    foreach (GameConfig::crewRoleDefinitions() as $code => $definition) {
        $runner->assertTrue($code !== '');
        $runner->assertTrue(isset($definition['name']));
        $runner->assertTrue(isset($definition['description']));
        $runner->assertTrue(isset($definition['stats']));
        $runner->assertTrue(count($definition['stats']) >= 2);
    }
});

$runner->test('Success chance never falls below five percent', function () use (
    $runner,
    $baseTemplate,
    $baseUser
): void {
    $template = $baseTemplate;
    $template['base_success_rate'] = -200;
    $template['difficulty'] = 100;

    $calculator = new DirtyJobCalculator(new SequenceRandomSource(50, 2));
    $result = $calculator->calculate(
        $template,
        $baseUser,
        ['police_presence' => 100],
        [],
        [],
        []
    );

    $runner->assertSame(5, $result['success_chance']);
});

$runner->test('Success chance never exceeds ninety-five percent', function () use (
    $runner,
    $baseTemplate,
    $baseUser
): void {
    $template = $baseTemplate;
    $template['base_success_rate'] = 200;
    $template['difficulty'] = 0;

    $calculator = new DirtyJobCalculator(new SequenceRandomSource(50, 2));
    $result = $calculator->calculate(
        $template,
        $baseUser,
        ['police_presence' => 0],
        [],
        [['success_bonus' => 100]],
        []
    );

    $runner->assertSame(95, $result['success_chance']);
});

$runner->test('Higher crew skills improve calculated success chance', function () use (
    $runner,
    $baseTemplate,
    $baseUser,
    $baseDistrict
): void {
    $weakCrew = [[
        'role_code' => 'infiltrator',
        'member' => [
            'stealth' => 20,
            'intelligence' => 20,
            'discipline' => 20,
            'health' => 100,
            'morale' => 60,
        ],
    ]];

    $strongCrew = [[
        'role_code' => 'infiltrator',
        'member' => [
            'stealth' => 80,
            'intelligence' => 80,
            'discipline' => 80,
            'health' => 100,
            'morale' => 60,
        ],
    ]];

    $weak = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        $weakCrew,
        [],
        []
    );
    $strong = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        $strongCrew,
        [],
        []
    );

    $runner->assertGreaterThan(
        $weak['success_chance'],
        $strong['success_chance']
    );
});

$runner->test('Positive traits improve calculated success chance', function () use (
    $runner,
    $baseTemplate,
    $baseUser,
    $baseDistrict
): void {
    $plain = [[
        'role_code' => 'lookout',
        'member' => [
            'street_knowledge' => 50,
            'intelligence' => 50,
            'discipline' => 50,
            'health' => 100,
            'morale' => 60,
        ],
        'trait_effects' => [],
    ]];
    $withTrait = $plain;
    $withTrait[0]['trait_effects'] = ['success_bonus' => 8];

    $plainResult = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        $plain,
        [],
        []
    );
    $traitResult = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        $withTrait,
        [],
        []
    );

    $runner->assertGreaterThan(
        $plainResult['success_chance'],
        $traitResult['success_chance']
    );
});

$runner->test('Burglary equipment improves burglary success chance', function () use (
    $runner,
    $baseTemplate,
    $baseUser,
    $baseDistrict
): void {
    $withoutEquipment = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        [],
        [],
        []
    );
    $withEquipment = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        [],
        [],
        [[
            'effects' => [
                'burglary_bonus' => 8,
                'stealth_entry_bonus' => 4,
            ],
        ]]
    );

    $runner->assertGreaterThan(
        $withoutEquipment['success_chance'],
        $withEquipment['success_chance']
    );
});

$runner->test('Preparation bonuses improve success and can lower heat', function () use (
    $runner,
    $baseTemplate,
    $baseUser,
    $baseDistrict
): void {
    $withoutPreparation = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        [],
        [],
        []
    );
    $withPreparation = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        [],
        [[
            'success_bonus' => 10,
            'heat_modifier' => -2,
        ]],
        []
    );

    $runner->assertGreaterThan(
        $withoutPreparation['success_chance'],
        $withPreparation['success_chance']
    );
    $runner->assertLessThan(
        $withoutPreparation['heat'],
        $withPreparation['heat']
    );
});

$runner->test('Higher district police presence reduces success chance', function () use (
    $runner,
    $baseTemplate,
    $baseUser
): void {
    $lowPolice = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        ['police_presence' => 10],
        [],
        [],
        []
    );
    $highPolice = (new DirtyJobCalculator(new SequenceRandomSource(50, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        ['police_presence' => 90],
        [],
        [],
        []
    );

    $runner->assertLessThan(
        $lowPolice['success_chance'],
        $highPolice['success_chance']
    );
});

$outcomeCases = [
    'critical success' => [1, 'critical_success'],
    'normal success' => [30, 'success'],
    'partial success' => [65, 'partial_success'],
    'failure' => [90, 'failure'],
    'critical failure' => [99, 'critical_failure'],
];

foreach ($outcomeCases as $label => [$roll, $expectedOutcome]) {
    $runner->test("Calculator produces {$label}", function () use (
        $runner,
        $baseTemplate,
        $baseUser,
        $roll,
        $expectedOutcome
    ): void {
        $template = $baseTemplate;
        $template['base_success_rate'] = 60;
        $template['difficulty'] = 0;

        $calculator = new DirtyJobCalculator(new SequenceRandomSource($roll, 2));
        $result = $calculator->calculate(
            $template,
            ['intelligence' => 0, 'heat' => 0],
            ['police_presence' => 45],
            [],
            [],
            []
        );

        $runner->assertSame($expectedOutcome, $result['outcome']);
    });
}

$runner->test('Critical failure adds more heat than normal success', function () use (
    $runner,
    $baseTemplate,
    $baseUser,
    $baseDistrict
): void {
    $success = (new DirtyJobCalculator(new SequenceRandomSource(30, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        [],
        [],
        []
    );
    $failure = (new DirtyJobCalculator(new SequenceRandomSource(99, 2)))->calculate(
        $baseTemplate,
        $baseUser,
        $baseDistrict,
        [],
        [],
        []
    );

    $runner->assertGreaterThan($success['heat'], $failure['heat']);
});

exit($runner->finish());
