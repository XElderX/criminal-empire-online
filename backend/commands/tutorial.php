<?php

require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\App;
use App\Core\Database;

App::boot(dirname(__DIR__));

if (App::env('APP_ENV', 'production') !== 'local') {
    fwrite(STDERR, "Tutorial reset is only available when APP_ENV=local.\n");
    exit(1);
}

$command = $argv[1] ?? '';
$identifier = $argv[2] ?? '';

if ($command !== 'reset' || $identifier === '') {
    fwrite(
        STDERR,
        "Usage: php commands/tutorial.php reset <user-id-or-email>\n"
    );
    exit(1);
}

$pdo = Database::pdo();
$userStatement = $pdo->prepare(
    <<<'SQL'
        SELECT id, email, username
        FROM users
        WHERE id = ? OR email = ?
        LIMIT 1
    SQL
);
$userStatement->execute([(int) $identifier, $identifier]);
$user = $userStatement->fetch();

if (!$user) {
    fwrite(STDERR, "User not found.\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $pdo->prepare(
        'DELETE FROM tutorial_step_logs WHERE user_id = ?'
    )->execute([$user['id']]);

    $pdo->prepare(
        <<<'SQL'
            INSERT INTO user_tutorial_progress (
                user_id,
                status,
                current_step_code,
                completed_steps,
                rewards_claimed,
                started_at,
                completed_at,
                skipped_at,
                updated_at
            ) VALUES (
                ?, 'active', 'welcome', JSON_ARRAY(), JSON_ARRAY(),
                NOW(), NULL, NULL, NOW()
            )
            ON DUPLICATE KEY UPDATE
                status = 'active',
                current_step_code = 'welcome',
                completed_steps = JSON_ARRAY(),
                rewards_claimed = JSON_ARRAY(),
                started_at = NOW(),
                completed_at = NULL,
                skipped_at = NULL,
                updated_at = NOW()
        SQL
    )->execute([$user['id']]);

    $pdo->commit();

    echo json_encode([
        'message' => 'Tutorial reset.',
        'user_id' => (int) $user['id'],
        'email' => $user['email'],
    ], JSON_PRETTY_PRINT);
    echo PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
