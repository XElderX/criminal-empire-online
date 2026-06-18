<?php

require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Services\CrewAgingService;
use App\Services\PortraitAssignmentService;
use App\Services\PortraitManifestService;

App::boot(dirname(__DIR__));

$command = $argv[1] ?? 'status';

try {
    $result = match ($command) {
        'status' => portraitStatus(),
        'backfill' => (new PortraitAssignmentService())->backfillAll(),
        'validate' => (new PortraitManifestService())->validate(),
        'sync-stages' => (new CrewAgingService())->synchronizeCurrentYear(),
        'age-one-year' => (new CrewAgingService())->advanceOneYear(),
        default => null,
    };

    if ($result === null) {
        fwrite(
            STDERR,
            "Usage: php commands/crew-portraits.php "
                . "status|backfill|validate|sync-stages|age-one-year\n"
        );
        exit(1);
    }

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
    echo PHP_EOL;

    if ($command === 'validate' && !($result['valid'] ?? false)) {
        exit(2);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

function portraitStatus(): array
{
    $pdo = Database::pdo();
    $manifest = new PortraitManifestService();
    $validation = $manifest->validate();

    return [
        'database' => [
            'total_npcs' => (int) $pdo->query(
                'SELECT COUNT(*) FROM npcs'
            )->fetchColumn(),
            'assigned' => (int) $pdo->query(
                'SELECT COUNT(*) FROM npcs WHERE portrait_set_key IS NOT NULL'
            )->fetchColumn(),
            'missing' => (int) $pdo->query(
                'SELECT COUNT(*) FROM npcs WHERE portrait_set_key IS NULL'
            )->fetchColumn(),
            'male_without_portrait' => (int) $pdo->query(
                <<<'SQL'
                    SELECT COUNT(*)
                    FROM npcs
                    WHERE LOWER(gender) = 'male'
                      AND portrait_set_key IS NULL
                SQL
            )->fetchColumn(),
            'female_without_portrait' => (int) $pdo->query(
                <<<'SQL'
                    SELECT COUNT(*)
                    FROM npcs
                    WHERE LOWER(gender) = 'female'
                      AND portrait_set_key IS NULL
                SQL
            )->fetchColumn(),
            'gender_mismatch_count' => portraitGenderMismatchCount($pdo),
        ],
        'manifest' => [
            'portrait_sets' => $validation['portrait_sets'],
            'complete_five_stage_sets' => $validation['complete_five_stage_sets'],
            'missing_stage_count' => count($validation['warnings']),
            'valid' => $validation['valid'],
        ],
    ];
}


function portraitGenderMismatchCount(PDO $pdo): int
{
    $manifest = (new PortraitManifestService())->allSets();
    $portraitGender = [];

    foreach ($manifest as $key => $set) {
        $portraitGender[$key] = $set['gender'] ?? null;
    }

    $rows = $pdo->query(
        <<<'SQL'
            SELECT gender, portrait_set_key
            FROM npcs
            WHERE portrait_set_key IS NOT NULL
        SQL
    )->fetchAll();
    $mismatches = 0;

    foreach ($rows as $row) {
        $npcGender = (new PortraitManifestService())->normalizeGender(
            $row['gender'] ?? null
        );
        $setGender = $portraitGender[$row['portrait_set_key']] ?? null;

        if ($npcGender !== null && $setGender !== $npcGender) {
            $mismatches++;
        }
    }

    return $mismatches;
}
