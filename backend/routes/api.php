<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CrimeController;
use App\Controllers\CrewController;
use App\Controllers\DirtyJobController;
use App\Controllers\EconomyController;
use App\Controllers\GangController;
use App\Controllers\HeatController;
use App\Controllers\ItemController;
use App\Controllers\JobController;
use App\Controllers\MarketController;
use App\Controllers\RecruitmentController;
use App\Controllers\ShopController;
use App\Controllers\TerritoryController;
use App\Controllers\TutorialController;
use App\Controllers\WarehouseController;
use App\Middleware\AuthMiddleware;

$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->get('/api/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

$router->get(
    '/api/tutorial',
    [TutorialController::class, 'state'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/tutorial/advance',
    [TutorialController::class, 'advance'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/tutorial/skip',
    [TutorialController::class, 'skip'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/tutorial/reopen',
    [TutorialController::class, 'reopen'],
    [AuthMiddleware::class]
);

$router->get('/api/jobs', [JobController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/jobs/active', [JobController::class, 'active'], [AuthMiddleware::class]);
$router->post(
    '/api/jobs/{id}/start',
    [JobController::class, 'start'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/job-runs/{id}/complete',
    [JobController::class, 'complete'],
    [AuthMiddleware::class]
);

$router->get(
    '/api/dirty-jobs',
    [DirtyJobController::class, 'index'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/dirty-jobs/active',
    [DirtyJobController::class, 'active'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/dirty-jobs/history',
    [DirtyJobController::class, 'history'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/dirty-jobs/{id}',
    [DirtyJobController::class, 'show'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/dirty-jobs/{id}/accept',
    [DirtyJobController::class, 'accept'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/dirty-job-runs/{id}/prepare',
    [DirtyJobController::class, 'prepare'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/dirty-job-runs/{id}/assign-crew',
    [DirtyJobController::class, 'assignCrew'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/dirty-job-runs/{id}/execute',
    [DirtyJobController::class, 'execute'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/dirty-job-runs/{id}/decision',
    [DirtyJobController::class, 'decide'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/dirty-job-runs/{id}/resolve',
    [DirtyJobController::class, 'resolve'],
    [AuthMiddleware::class]
);

$router->get(
    '/api/recruitment',
    [RecruitmentController::class, 'index'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/recruitment/{id}/hire',
    [RecruitmentController::class, 'hire'],
    [AuthMiddleware::class]
);

$router->get('/api/my-gang', [CrewController::class, 'index'], [AuthMiddleware::class]);
$router->get(
    '/api/my-gang/{id}',
    [CrewController::class, 'show'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/my-gang/{id}/equip',
    [CrewController::class, 'equip'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/my-gang/{id}/equipment/{equipmentId}/unequip',
    [CrewController::class, 'unequip'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/my-gang/{id}/dismiss',
    [CrewController::class, 'dismiss'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/my-gang/{id}/history',
    [CrewController::class, 'history'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/my-gang/{id}/pay-overdue',
    [CrewController::class, 'payOverdue'],
    [AuthMiddleware::class]
);

$router->get('/api/items', [ItemController::class, 'shop'], [AuthMiddleware::class]);
$router->post(
    '/api/items/{id}/buy',
    [ItemController::class, 'buy'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/inventory',
    [ItemController::class, 'inventory'],
    [AuthMiddleware::class]
);

$router->get('/api/weapons', [ShopController::class, 'weapons'], [AuthMiddleware::class]);
$router->post(
    '/api/weapons/{id}/buy',
    [ShopController::class, 'buyWeapon'],
    [AuthMiddleware::class]
);

$router->get(
    '/api/warehouses',
    [WarehouseController::class, 'index'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/warehouse-listings',
    [WarehouseController::class, 'listings'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/warehouse-listings/{id}/purchase',
    [WarehouseController::class, 'purchase'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/warehouses/{id}/transfer',
    [WarehouseController::class, 'transfer'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/warehouses/{id}/vehicles/{vehicleId}/store',
    [WarehouseController::class, 'storeVehicle'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/warehouses/{id}/vehicles/{vehicleId}/remove',
    [WarehouseController::class, 'removeVehicle'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/warehouses/{id}/upgrades/{upgradeId}/purchase',
    [WarehouseController::class, 'purchaseUpgrade'],
    [AuthMiddleware::class]
);

$router->post(
    '/api/heat/lay-low',
    [HeatController::class, 'layLow'],
    [AuthMiddleware::class]
);

$router->get('/api/crimes', [CrimeController::class, 'index'], [AuthMiddleware::class]);
$router->post(
    '/api/crimes/{id}/commit',
    [CrimeController::class, 'commit'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/crime-logs',
    [CrimeController::class, 'logs'],
    [AuthMiddleware::class]
);

$router->get('/api/drug-market', [MarketController::class, 'drugs'], [AuthMiddleware::class]);
$router->get('/api/gangs', [GangController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/gangs', [GangController::class, 'create'], [AuthMiddleware::class]);
$router->get(
    '/api/territories',
    [TerritoryController::class, 'index'],
    [AuthMiddleware::class]
);

$router->get(
    '/api/admin/dashboard',
    [AdminController::class, 'dashboard'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/admin/audit',
    [AdminController::class, 'audit'],
    [AuthMiddleware::class]
);

$router->get(
    '/api/admin/item-catalog',
    [AdminController::class, 'itemCatalog'],
    [AuthMiddleware::class]
);

$router->post(
    '/api/admin/users/{id}/energy/refill',
    [AdminController::class, 'refillEnergy'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/admin/users/{id}/cash/set',
    [AdminController::class, 'setCash'],
    [AuthMiddleware::class]
);

$router->post(
    '/api/admin/users/{id}/grant-asset',
    [AdminController::class, 'grantAsset'],
    [AuthMiddleware::class]
);

$router->get(
    '/api/admin/economy',
    [EconomyController::class, 'status'],
    [AuthMiddleware::class]
);
