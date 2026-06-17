<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoload.php';
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/PredictableRandomSource.php';

use App\Core\App;
use App\Core\Database;
use App\Services\AuthService;
use App\Services\CrewService;
use App\Services\DirtyJobService;
use App\Services\ItemService;
use App\Services\RecruitmentService;
use App\Services\TutorialService;
use App\Services\WarehouseService;
use Tests\PredictableRandomSource;
use Tests\TestRunner;

$runner = new TestRunner();

if (!in_array('mysql', \PDO::getAvailableDrivers(), true)) {
    $runner->test('MySQL integration environment is available', function () use ($runner): void {
        $runner->skip('PDO MySQL is not installed. Install php-mysql and run again.');
    });

    exit($runner->finish());
}

$config = testDatabaseConfiguration();
assertSafeTestDatabaseName($config['database']);
recreateTestDatabase($config);
configureApplication($config);
loadSchemaAndSeeds();

$authService = new AuthService();
$tutorialService = new TutorialService();
$recruitmentService = new RecruitmentService();
$itemService = new ItemService();
$crewService = new CrewService();
$warehouseService = new WarehouseService();
$dirtyJobService = new DirtyJobService(new PredictableRandomSource());

$primaryRegistration = $authService->register([
    'username' => 'integration_player',
    'email' => 'integration-player@example.test',
    'password' => 'password123',
]);
$primaryUser = freshUser((int) $primaryRegistration['user']['id']);

$runner->test('New user starts with exactly 500 cash', function () use (
    $runner,
    $primaryUser
): void {
    $runner->assertSame(500, (int) $primaryUser['cash']);
    $runner->assertSame(0, (int) $primaryUser['bank_cash']);
    $runner->assertSame(0, (int) $primaryUser['dirty_money']);
});

$runner->test('New user receives active tutorial state', function () use (
    $runner,
    $tutorialService,
    $primaryUser
): void {
    $state = $tutorialService->state($primaryUser);
    $runner->assertSame('active', $state['status']);
    $runner->assertSame('welcome', $state['current_step_code']);
});

$runner->test('Tutorial cannot jump over the current step', function () use (
    $runner,
    $tutorialService,
    $primaryUser
): void {
    expectRuntimeException(
        static fn () => $tutorialService->advance(
            $primaryUser,
            'first_money',
            true
        )
    );

    $runner->assertSame(
        'welcome',
        $tutorialService->state($primaryUser)['current_step_code']
    );
});

$runner->test('Acknowledged welcome step persists', function () use (
    $runner,
    $tutorialService,
    $primaryUser
): void {
    $state = $tutorialService->advance($primaryUser, 'welcome', true);
    $runner->assertSame('first_money', $state['current_step_code']);
    $runner->assertTrue(in_array('welcome', $state['completed_steps'], true));
});

$skipRegistration = $authService->register([
    'username' => 'tutorial_skipper',
    'email' => 'tutorial-skipper@example.test',
    'password' => 'password123',
]);
$skipUser = freshUser((int) $skipRegistration['user']['id']);

$runner->test('Tutorial skip persists and grants no completion cash', function () use (
    $runner,
    $tutorialService,
    $skipUser
): void {
    $beforeCash = (int) $skipUser['cash'];
    $state = $tutorialService->skip($skipUser);
    $afterUser = freshUser((int) $skipUser['id']);

    $runner->assertSame('skipped', $state['status']);
    $runner->assertSame($beforeCash, (int) $afterUser['cash']);
});

Database::pdo()->prepare(
    <<<'SQL'
        UPDATE users
        SET
            cash = 30000,
            reputation = 60,
            level = 5,
            intelligence = 30,
            energy = max_energy,
            updated_at = NOW()
        WHERE id = ?
    SQL
)->execute([$primaryUser['id']]);
$primaryUser = freshUser((int) $primaryUser['id']);

$candidates = $recruitmentService->candidates($primaryUser);
$firstCandidate = $candidates[0] ?? null;

$runner->test('Recruitment seed provides an affordable candidate', function () use (
    $runner,
    $firstCandidate
): void {
    $runner->assertTrue(is_array($firstCandidate));
    $runner->assertTrue((int) $firstCandidate['recruitment_fee'] <= 500);
});

$hireResult = $recruitmentService->hire(
    $primaryUser,
    (int) $firstCandidate['id']
);
$memberId = (int) $hireResult['gang_member_id'];
$primaryUser = freshUser((int) $primaryUser['id']);

$runner->test('Hiring creates one active persistent crew member', function () use (
    $runner,
    $crewService,
    $primaryUser,
    $memberId
): void {
    $member = $crewService->member($primaryUser, $memberId);
    $runner->assertSame('active', $member['status']);
    $runner->assertTrue($member['biography'] !== '');
});

$runner->test('Candidate cannot be hired twice', function () use (
    $recruitmentService,
    $primaryUser,
    $firstCandidate
): void {
    expectRuntimeException(
        static fn () => $recruitmentService->hire(
            $primaryUser,
            (int) $firstCandidate['id']
        )
    );
});

$shop = $itemService->shop($primaryUser);
$crowbar = findByCode($shop, 'crowbar');
$gloves = findByCode($shop, 'work_gloves');
$itemService->buy($primaryUser, (int) $crowbar['id'], 1);
$itemService->buy($primaryUser, (int) $gloves['id'], 1);
$primaryUser = freshUser((int) $primaryUser['id']);
$equipResult = $crewService->equip(
    $primaryUser,
    $memberId,
    'item',
    (int) $gloves['id']
);

$runner->test('Owned compatible equipment can be equipped', function () use (
    $runner,
    $equipResult
): void {
    $runner->assertSame('clothing', $equipResult['slot']);
});

$runner->test('Unowned equipment cannot be equipped', function () use (
    $crewService,
    $primaryUser,
    $memberId
): void {
    $unownedItemId = (int) Database::pdo()->query(
        "SELECT id FROM item_definitions WHERE code = 'basic_vest' LIMIT 1"
    )->fetchColumn();

    expectRuntimeException(
        static fn () => $crewService->equip(
            $primaryUser,
            $memberId,
            'item',
            $unownedItemId
        )
    );
});

$listings = $warehouseService->listings($primaryUser);
$entryListing = $listings[0];
$cashBeforeWarehouse = (int) $primaryUser['cash'];
$purchaseResult = $warehouseService->purchase(
    $primaryUser,
    (int) $entryListing['id']
);
$warehouseId = (int) $purchaseResult['warehouse_id'];
$primaryUser = freshUser((int) $primaryUser['id']);

$runner->test('Warehouse purchase deducts the exact listing price', function () use (
    $runner,
    $cashBeforeWarehouse,
    $entryListing,
    $primaryUser
): void {
    $runner->assertSame(
        $cashBeforeWarehouse - (int) $entryListing['purchase_price'],
        (int) $primaryUser['cash']
    );
});

$runner->test('Duplicate warehouse purchase is rejected', function () use (
    $warehouseService,
    $primaryUser,
    $entryListing
): void {
    expectRuntimeException(
        static fn () => $warehouseService->purchase(
            $primaryUser,
            (int) $entryListing['id']
        )
    );
});

$runner->test('Warehouse rejects another player owner id', function () use (
    $warehouseService,
    $skipUser,
    $warehouseId,
    $crowbar
): void {
    expectRuntimeException(
        static fn () => $warehouseService->transfer(
            $skipUser,
            $warehouseId,
            'deposit',
            'item',
            (int) $crowbar['id'],
            1
        )
    );
});

$warehouseService->transfer(
    $primaryUser,
    $warehouseId,
    'deposit',
    'item',
    (int) $crowbar['id'],
    1
);

$runner->test('Item deposit persists in warehouse storage', function () use (
    $runner,
    $warehouseService,
    $primaryUser,
    $crowbar
): void {
    $overview = $warehouseService->overview($primaryUser);
    $warehouse = $overview['warehouses'][0];
    $stored = array_values(array_filter(
        $warehouse['storage'],
        static fn (array $row): bool => $row['asset_type'] === 'item'
            && (int) $row['asset_id'] === (int) $crowbar['id']
    ));

    $runner->assertSame(1, (int) $stored[0]['quantity']);
});

$runner->test('Negative warehouse quantities are rejected', function () use (
    $warehouseService,
    $primaryUser,
    $warehouseId,
    $crowbar
): void {
    expectRuntimeException(
        static fn () => $warehouseService->transfer(
            $primaryUser,
            $warehouseId,
            'withdraw',
            'item',
            (int) $crowbar['id'],
            -1
        )
    );
});

$warehouseService->transfer(
    $primaryUser,
    $warehouseId,
    'withdraw',
    'item',
    (int) $crowbar['id'],
    1
);

$runner->test('Warehouse withdrawal restores personal inventory', function () use (
    $runner,
    $itemService,
    $primaryUser,
    $crowbar
): void {
    $inventory = $itemService->inventory($primaryUser);
    $restored = array_values(array_filter(
        $inventory['items'],
        static fn (array $row): bool => (int) $row['id'] === (int) $crowbar['id']
    ));

    $runner->assertSame(1, (int) $restored[0]['quantity']);
});

$opportunities = $dirtyJobService->opportunities($primaryUser);
$burglary = findByCode($opportunities, 'apartment_burglary');
$acceptResult = $dirtyJobService->accept(
    $primaryUser,
    (int) $burglary['id'],
    'integration-apartment-burglary'
);
$runId = (int) $acceptResult['dirty_job_run_id'];

$runner->test('Accepted Dirty Job enters accepted state', function () use (
    $runner,
    $acceptResult
): void {
    $runner->assertSame('accepted', $acceptResult['run']['status']);
});

$dirtyJobService->prepare($primaryUser, $runId, 'scout_building');
$dirtyJobService->assignCrew(
    $primaryUser,
    $runId,
    [[
        'member_id' => $memberId,
        'role_code' => 'infiltrator',
    ]]
);
$execution = $dirtyJobService->startExecution($primaryUser, $runId);

$runner->test('Executing Dirty Job marks assigned member busy', function () use (
    $runner,
    $crewService,
    $primaryUser,
    $memberId,
    $execution
): void {
    $runner->assertSame('executing', $execution['run']['status']);
    $member = $crewService->member($primaryUser, $memberId);
    $runner->assertSame('busy', $member['status']);
});

Database::pdo()->prepare(
    'UPDATE dirty_job_runs SET completes_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE id = ?'
)->execute([$runId]);

$firstResolve = $dirtyJobService->resolve($primaryUser, $runId);

if (($firstResolve['status'] ?? '') === 'awaiting_decision') {
    $detail = $dirtyJobService->detail($primaryUser, (int) $burglary['id']);
    $eventOptions = $detail['run']['event']['options'] ?? [];
    $decisionCode = (string) ($eventOptions[0]['code'] ?? '');
    $dirtyJobService->submitDecision($primaryUser, $runId, $decisionCode);
    $firstResolve = $dirtyJobService->resolve($primaryUser, $runId);
}

$runner->test('Dirty Job resolves to a terminal state', function () use (
    $runner,
    $firstResolve
): void {
    $runner->assertTrue(in_array(
        $firstResolve['status'],
        ['completed', 'partially_completed', 'failed'],
        true
    ));
});

$runner->test('Crew member is released or receives a managed consequence', function () use (
    $runner,
    $crewService,
    $primaryUser,
    $memberId
): void {
    $member = $crewService->member($primaryUser, $memberId);
    $runner->assertTrue(in_array(
        $member['status'],
        ['active', 'injured', 'arrested'],
        true
    ));
    $runner->assertSame(null, $member['current_assignment_id']);
});

$runner->test('Dirty Job cannot resolve twice', function () use (
    $dirtyJobService,
    $primaryUser,
    $runId
): void {
    expectRuntimeException(
        static fn () => $dirtyJobService->resolve($primaryUser, $runId)
    );
});

$runner->test('Crew dismissal preserves row and history', function () use (
    $runner,
    $crewService,
    $primaryUser,
    $memberId
): void {
    Database::pdo()->prepare(
        <<<'SQL'
            UPDATE player_gang_members
            SET
                status = 'active',
                current_assignment_type = NULL,
                current_assignment_id = NULL,
                recovering_until = NULL,
                arrested_until = NULL
            WHERE id = ?
        SQL
    )->execute([$memberId]);

    $crewService->dismiss($primaryUser, $memberId, 'Integration-test dismissal');

    $row = Database::pdo()->prepare(
        'SELECT status, dismissal_reason FROM player_gang_members WHERE id = ?'
    );
    $row->execute([$memberId]);
    $dismissed = $row->fetch();

    $runner->assertSame('dismissed', $dismissed['status']);
    $runner->assertSame('Integration-test dismissal', $dismissed['dismissal_reason']);
    $runner->assertTrue(count($crewService->historyForMember($primaryUser, $memberId)) > 0);
});


Database::pdo()->prepare(
    <<<'SQL'
        UPDATE recruitment_candidates
        SET
            available_from = DATE_SUB(NOW(), INTERVAL 1 DAY),
            expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
        WHERE id = ?
    SQL
)->execute([$firstCandidate['id']]);
$primaryUser = freshUser((int) $primaryUser['id']);
$rehireResult = $recruitmentService->hire(
    $primaryUser,
    (int) $firstCandidate['id']
);

$runner->test('Dismissed crew can return without losing identity', function () use (
    $runner,
    $crewService,
    $primaryUser,
    $memberId,
    $rehireResult
): void {
    $runner->assertSame($memberId, (int) $rehireResult['gang_member_id']);
    $member = $crewService->member($primaryUser, $memberId);
    $runner->assertSame('active', $member['status']);

    $titles = array_column($member['history'], 'title');
    $runner->assertTrue(in_array('Dismissed from the crew', $titles, true));
    $runner->assertTrue(in_array('Rejoined the crew', $titles, true));
});

exit($runner->finish());

/** @return array{host:string,port:string,database:string,username:string,password:string} */
function testDatabaseConfiguration(): array
{
    return [
        'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('TEST_DB_PORT') ?: '3306',
        'database' => getenv('TEST_DB_DATABASE') ?: 'criminal_empire_test',
        'username' => getenv('TEST_DB_USERNAME') ?: 'root',
        'password' => getenv('TEST_DB_PASSWORD') ?: '',
    ];
}

function assertSafeTestDatabaseName(string $database): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+_test$/', $database)) {
        throw new \RuntimeException(
            'TEST_DB_DATABASE must end in _test because this suite recreates it.'
        );
    }
}

function recreateTestDatabase(array $config): void
{
    $pdo = new \PDO(
        "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );

    $database = $config['database'];
    $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
    $pdo->exec(
        "CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
}

function configureApplication(array $config): void
{
    App::$basePath = dirname(__DIR__);
    App::$env = [
        'APP_ENV' => 'test',
        'DB_HOST' => $config['host'],
        'DB_PORT' => $config['port'],
        'DB_DATABASE' => $config['database'],
        'DB_USERNAME' => $config['username'],
        'DB_PASSWORD' => $config['password'],
        'JOB_DURATION_MULTIPLIER' => '0.01',
    ];

    $_ENV['JOB_DURATION_MULTIPLIER'] = '0.01';
}

function loadSchemaAndSeeds(): void
{
    $databaseRoot = dirname(__DIR__) . '/database';

    foreach (['migrations', 'seeders'] as $directory) {
        $files = glob("{$databaseRoot}/{$directory}/*.sql") ?: [];
        sort($files);

        foreach ($files as $file) {
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new \RuntimeException("Could not read {$file}");
            }

            Database::pdo()->exec($sql);
        }
    }
}

/** @return array<string, mixed> */
function freshUser(int $userId): array
{
    $statement = Database::pdo()->prepare(
        'SELECT * FROM users WHERE id = ? LIMIT 1'
    );
    $statement->execute([$userId]);
    $user = $statement->fetch();

    if (!$user) {
        throw new \RuntimeException('Test user not found.');
    }

    return $user;
}

/** @param list<array<string, mixed>> $rows */
function findByCode(array $rows, string $code): array
{
    foreach ($rows as $row) {
        if (($row['code'] ?? null) === $code) {
            return $row;
        }
    }

    throw new \RuntimeException("Could not find seeded code: {$code}");
}

function expectRuntimeException(callable $callback): void
{
    try {
        $callback();
    } catch (\RuntimeException) {
        return;
    } catch (\Throwable $exception) {
        throw new \RuntimeException(
            'Expected RuntimeException, got ' . $exception::class
            . ': ' . $exception->getMessage()
        );
    }

    throw new \RuntimeException('Expected RuntimeException was not thrown.');
}
