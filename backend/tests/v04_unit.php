<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Core\App;
use App\Services\CrimeNarrativeService;
use App\Services\CrimeRiskCalculator;
use Tests\TestRunner;

App::boot(dirname(__DIR__));

$runner = new TestRunner();
$calculator = new CrimeRiskCalculator();
$narrative = new CrimeNarrativeService();

templateTest($runner, $calculator);
equipmentTest($runner, $calculator);
preparationTest($runner, $calculator);
qualityTest($runner, $calculator);
narrativeTest($runner, $narrative);

exit($runner->finish());

function templateTest(TestRunner $runner, CrimeRiskCalculator $calculator): void
{
    $runner->test('Crew stats improve v0.4 crime success chance', function () use ($runner, $calculator): void {
        $template = templateFixture();
        $weak = $calculator->calculate($template, [crewFixture(25)], [], [], ['heat' => 10, 'quality' => 'normal']);
        $strong = $calculator->calculate($template, [crewFixture(80)], [], [], ['heat' => 10, 'quality' => 'normal']);

        $runner->assertGreaterThan($weak['success_chance'], $strong['success_chance']);
    });
}

function equipmentTest(TestRunner $runner, CrimeRiskCalculator $calculator): void
{
    $runner->test('Equipment effects modify success and police risk', function () use ($runner, $calculator): void {
        $template = templateFixture();
        $plain = $calculator->calculate($template, [crewFixture(55)], [], [], ['heat' => 10, 'quality' => 'normal']);
        $equipped = $calculator->calculate($template, [crewFixture(55)], [[
            'effects' => ['stealth' => 8, 'police_risk' => -4, 'loot_capacity' => 8],
        ]], [], ['heat' => 10, 'quality' => 'normal']);

        $runner->assertGreaterThan($plain['success_chance'], $equipped['success_chance']);
        $runner->assertLessThan($plain['police_chance'], $equipped['police_chance']);
        $runner->assertGreaterThan($plain['loot_modifier'], $equipped['loot_modifier']);
    });
}

function preparationTest(TestRunner $runner, CrimeRiskCalculator $calculator): void
{
    $runner->test('Preparation can lower disaster probability', function () use ($runner, $calculator): void {
        $template = templateFixture();
        $unprepared = $calculator->calculate($template, [], [], [], ['heat' => 70, 'quality' => 'weak']);
        $prepared = $calculator->calculate($template, [], [], [[
            'effects' => ['success' => 5, 'disaster' => -4, 'police' => -3],
        ]], ['heat' => 70, 'quality' => 'weak']);

        $runner->assertLessThan($unprepared['disaster_chance'], $prepared['disaster_chance']);
        $runner->assertLessThan($unprepared['police_chance'], $prepared['police_chance']);
    });
}

function qualityTest(TestRunner $runner, CrimeRiskCalculator $calculator): void
{
    $runner->test('Trap-quality information is riskier than strong information', function () use ($runner, $calculator): void {
        $template = templateFixture();
        $strong = $calculator->calculate($template, [crewFixture(55)], [], [], ['heat' => 20, 'quality' => 'strong']);
        $trap = $calculator->calculate($template, [crewFixture(55)], [], [], ['heat' => 20, 'quality' => 'trap']);

        $runner->assertGreaterThan($trap['success_chance'], $strong['success_chance']);
        $runner->assertGreaterThan($strong['disaster_chance'], $trap['disaster_chance']);
        $runner->assertGreaterThan($strong['police_chance'], $trap['police_chance']);
    });
}

function narrativeTest(TestRunner $runner, CrimeNarrativeService $narrative): void
{
    $runner->test('Crime event choices are backend-owned and validated by code', function () use ($runner, $narrative): void {
        $event = $narrative->event('police_patrol');
        $runner->assertSame('police_patrol', $event['code']);
        $runner->assertGreaterThan(2, count($event['choices']));
    });

    $runner->test('Preparation options expose structured effects', function () use ($runner, $narrative): void {
        $options = $narrative->preparationOptions();
        $runner->assertGreaterThan(4, count($options));
        $runner->assertTrue(array_key_exists('effects', $options[0]));
    });
}

function templateFixture(): array
{
    return [
        'base_success_rate' => 55,
        'base_disaster_chance' => 8,
        'min_crew' => 1,
        'max_crew' => 3,
        'relevant_stats' => ['stealth', 'driving', 'discipline'],
    ];
}

function crewFixture(int $value): array
{
    return [
        'stealth' => $value,
        'driving' => $value,
        'discipline' => $value,
        'loyalty' => $value,
        'morale' => $value,
    ];
}
