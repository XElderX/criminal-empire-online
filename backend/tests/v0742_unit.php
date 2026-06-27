<?php

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';

use App\Config\GameConfig;
use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$runner->test('Version is v0.7.4.2', function () use ($runner): void {
    $runner->assertSame('0.7.4.2', GameConfig::VERSION);
    $runner->assertContains('Recruitment Identity Diversity Hotfix', GameConfig::RELEASE_TITLE);
});

$runner->test('Generated recruitment profiles use a larger identity pool', function () use ($runner, $root): void {
    $service = readFileOrFail($root . '/backend/app/Services/RecruitmentService.php');
    foreach (['nextGeneratedRecruitGender', 'generatedRecruitProfile', 'profileAlreadyExists', 'generatedRecruitOccupation'] as $needle) {
        $runner->assertContains($needle, $service);
    }

    foreach (['Darius', 'Lena', 'Marcus', 'Mara', 'Viktor', 'Irena', 'Tomas', 'Sofia', 'Rafi', 'Dana', 'Owen', 'Vera'] as $needle) {
        $runner->assertContains($needle, $service);
    }
});

$runner->test('Recruitment portraits are repaired when gender metadata mismatches', function () use ($runner, $root): void {
    $service = readFileOrFail($root . '/backend/app/Services/RecruitmentService.php');
    foreach (['portraitMatchesCandidateGender', 'portrait_set_key = NULL', 'PortraitManifestService'] as $needle) {
        $runner->assertContains($needle, $service);
    }
});

exit($runner->finish());

function readFileOrFail(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Could not read {$path}");
    }
    return $content;
}
