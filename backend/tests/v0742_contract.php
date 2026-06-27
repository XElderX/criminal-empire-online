<?php

require_once __DIR__ . '/TestRunner.php';

use Tests\TestRunner;

$runner = new TestRunner();
$root = dirname(__DIR__, 2);

$runner->test('Migration refreshes available generated recruit identities', function () use ($runner, $root): void {
    $migration = readFileOrFail($root . '/backend/database/migrations/021_v0742_recruitment_identity_diversity.sql');
    foreach (['world_recruit_refresh', 'portrait_set_key = NULL', 'Darius', 'Lena', 'profile_index', 'MOD(numbered.rn - 1, 24) + 1'] as $needle) {
        $runner->assertContains($needle, $migration);
    }
});

$runner->test('Documentation records recruitment identity diversity hotfix', function () use ($runner, $root): void {
    $docs = readFileOrFail($root . '/docs/DEVELOPMENT_LOG.md')
        . readFileOrFail($root . '/README.md')
        . readFileOrFail($root . '/backend/docs-api.md');

    foreach (['v0.7.4.2', 'Recruitment Identity Diversity Hotfix', 'male/female', 'larger first-name, surname, nickname'] as $needle) {
        $runner->assertContains($needle, $docs);
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
