<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Core\App;
use App\Services\CrewAgeStageResolver;
use App\Services\CrewPortraitResolver;
use App\Services\PortraitManifestService;
use Tests\TestRunner;

App::boot(dirname(__DIR__));

$runner = new TestRunner();
$ageResolver = new CrewAgeStageResolver();
$manifest = new PortraitManifestService();
$portraitResolver = new CrewPortraitResolver($manifest, $ageResolver);

$ageCases = [
    16 => CrewAgeStageResolver::VERY_YOUNG,
    24 => CrewAgeStageResolver::VERY_YOUNG,
    25 => CrewAgeStageResolver::YOUNG,
    31 => CrewAgeStageResolver::YOUNG,
    32 => CrewAgeStageResolver::ADULT,
    40 => CrewAgeStageResolver::ADULT,
    41 => CrewAgeStageResolver::MATURE,
    55 => CrewAgeStageResolver::MATURE,
    56 => CrewAgeStageResolver::ELDER,
    70 => CrewAgeStageResolver::ELDER,
];

foreach ($ageCases as $age => $expectedStage) {
    $runner->test("Age {$age} resolves to {$expectedStage}", function () use (
        $runner,
        $ageResolver,
        $age,
        $expectedStage
    ): void {
        $runner->assertSame($expectedStage, $ageResolver->stageKey($age));
    });
}

$runner->test('Ages below sixteen are not recruitable', function () use (
    $runner,
    $ageResolver
): void {
    $resolved = $ageResolver->resolve(15);

    $runner->assertSame(CrewAgeStageResolver::VERY_YOUNG, $resolved['key']);
    $runner->assertFalse((bool) $resolved['recruitable']);
    $runner->assertTrue((bool) $resolved['outside_standard_range']);
});

$runner->test('Ages above seventy retain the elder portrait', function () use (
    $runner,
    $ageResolver
): void {
    $resolved = $ageResolver->resolve(85);

    $runner->assertSame(CrewAgeStageResolver::ELDER, $resolved['key']);
    $runner->assertFalse((bool) $resolved['recruitable']);
    $runner->assertTrue((bool) $resolved['outside_standard_range']);
});

$runner->test('Negative ages are handled safely', function () use (
    $runner,
    $ageResolver
): void {
    $resolved = $ageResolver->resolve(-5);

    $runner->assertSame(0, $resolved['age']);
    $runner->assertSame(CrewAgeStageResolver::VERY_YOUNG, $resolved['key']);
    $runner->assertFalse((bool) $resolved['recruitable']);
});

$runner->test('Manifest contains fifty stable portrait identities', function () use (
    $runner,
    $manifest
): void {
    $sets = $manifest->allSets();

    $runner->assertSame(50, count($sets));
    $runner->assertSame(50, count(array_unique(array_keys($sets))));
});

$runner->test('Manifest contains strictly gendered male and female sets', function () use (
    $runner,
    $manifest
): void {
    $maleSets = $manifest->enabledSets('male');
    $femaleSets = $manifest->enabledSets('female');

    $runner->assertSame(32, count($maleSets));
    $runner->assertSame(18, count($femaleSets));

    foreach ($maleSets as $set) {
        $runner->assertSame('male', $set['gender']);
    }

    foreach ($femaleSets as $set) {
        $runner->assertSame('female', $set['gender']);
    }
});

$runner->test('Gender aliases normalize to the strict portrait categories', function () use (
    $runner,
    $manifest
): void {
    $runner->assertSame('male', $manifest->normalizeGender('M'));
    $runner->assertSame('female', $manifest->normalizeGender('woman'));
    $runner->assertSame(null, $manifest->normalizeGender('unknown'));
});

$runner->test('Adult art resolves directly for an adult crew member', function () use (
    $runner,
    $portraitResolver
): void {
    $portrait = $portraitResolver->resolve([
        'age' => 36,
        'gender' => 'male',
        'portrait_set_key' => 'portrait-set-001',
        'portrait_focal_x' => 50,
        'portrait_focal_y' => 42,
    ]);

    $runner->assertSame('portrait-set-001', $portrait['identity_key']);
    $runner->assertSame('adult', $portrait['stage']);
    $runner->assertSame('adult', $portrait['resolved_asset_stage']);
    $runner->assertFalse((bool) $portrait['uses_fallback']);
    $runner->assertFalse((bool) $portrait['uses_stage_fallback']);
});

$runner->test('Missing life-stage art safely uses the identity adult asset', function () use (
    $runner,
    $portraitResolver
): void {
    $portrait = $portraitResolver->resolve([
        'age' => 20,
        'gender' => 'female',
        'portrait_set_key' => 'portrait-set-003',
    ]);

    $runner->assertSame('very_young', $portrait['stage']);
    $runner->assertSame('adult', $portrait['resolved_asset_stage']);
    $runner->assertFalse((bool) $portrait['uses_fallback']);
    $runner->assertTrue((bool) $portrait['uses_stage_fallback']);
    $runner->assertContains('portrait-set-003/adult.webp', $portrait['url']);
});

$runner->test('Unknown portrait identity returns the neutral fallback', function () use (
    $runner,
    $portraitResolver
): void {
    $portrait = $portraitResolver->resolve([
        'age' => 48,
        'gender' => 'male',
        'portrait_set_key' => 'portrait-set-999',
    ]);

    $runner->assertSame('mature', $portrait['stage']);
    $runner->assertTrue((bool) $portrait['uses_fallback']);
    $runner->assertSame(
        PortraitManifestService::FALLBACK_URL,
        $portrait['url']
    );
});

$runner->test('Aging changes the stage without changing portrait identity', function () use (
    $runner,
    $portraitResolver
): void {
    $before = $portraitResolver->resolve([
        'age' => 24,
        'gender' => 'male',
        'portrait_set_key' => 'portrait-set-002',
    ]);
    $after = $portraitResolver->resolve([
        'age' => 25,
        'gender' => 'male',
        'portrait_set_key' => 'portrait-set-002',
    ]);

    $runner->assertSame($before['identity_key'], $after['identity_key']);
    $runner->assertSame('very_young', $before['stage']);
    $runner->assertSame('young', $after['stage']);
});

$runner->test('Manifest validation reports the supplied artwork honestly', function () use (
    $runner,
    $manifest
): void {
    $validation = $manifest->validate();

    $runner->assertTrue((bool) $validation['valid']);
    $runner->assertFalse((bool) $validation['complete']);
    $runner->assertSame(50, $validation['portrait_sets']);
    $runner->assertSame(0, $validation['complete_five_stage_sets']);
    $runner->assertSame(200, count($validation['warnings']));
    $runner->assertSame(0, count($validation['errors']));
});

exit($runner->finish());
