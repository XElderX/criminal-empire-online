<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BossController;
use App\Controllers\CrimeController;
use App\Controllers\CrewController;
use App\Controllers\DirtyJobController;
use App\Controllers\EconomyController;
use App\Controllers\GangController;
use App\Controllers\HeatController;
use App\Controllers\InvestigationController;
use App\Controllers\ItemController;
use App\Controllers\JobController;
use App\Controllers\MarketController;
use App\Controllers\QuickCrimeController;
use App\Controllers\RecruitmentController;
use App\Controllers\ShopController;
use App\Controllers\TerritoryController;
use App\Controllers\TutorialController;
use App\Controllers\UpdateNoticeController;
use App\Controllers\WorldMapController;
use App\Controllers\WarehouseController;
use App\Middleware\AuthMiddleware;

$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->get('/api/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

$router->get('/api/tutorial', [TutorialController::class, 'state'], [AuthMiddleware::class]);
$router->get('/api/tutorial/current', [TutorialController::class, 'current'], [AuthMiddleware::class]);
$router->get('/api/tutorial/steps', [TutorialController::class, 'steps'], [AuthMiddleware::class]);
$router->post('/api/tutorial/objective', [TutorialController::class, 'recordObjective'], [AuthMiddleware::class]);
$router->post('/api/tutorial/advance', [TutorialController::class, 'advance'], [AuthMiddleware::class]);
$router->post('/api/tutorial/skip', [TutorialController::class, 'skip'], [AuthMiddleware::class]);
$router->post('/api/tutorial/reopen', [TutorialController::class, 'reopen'], [AuthMiddleware::class]);
$router->post('/api/tutorial/reset-dev', [TutorialController::class, 'resetDev'], [AuthMiddleware::class]);
$router->get('/api/tutorial/guide', [TutorialController::class, 'guide'], [AuthMiddleware::class]);
$router->get('/api/help/tips', [TutorialController::class, 'tips'], [AuthMiddleware::class]);
$router->post('/api/help/tips/{tipKey}/dismiss', [TutorialController::class, 'dismissTip'], [AuthMiddleware::class]);
$router->post('/api/help/tips/{tipKey}/reopen', [TutorialController::class, 'reopenTip'], [AuthMiddleware::class]);

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


$router->get('/api/world-map', [WorldMapController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/world-map/regions', [WorldMapController::class, 'regions'], [AuthMiddleware::class]);
$router->get('/api/world-map/regions/{slug}', [WorldMapController::class, 'region'], [AuthMiddleware::class]);
$router->get('/api/world-map/regions/{slug}/locations', [WorldMapController::class, 'regionLocations'], [AuthMiddleware::class]);
$router->get('/api/world-map/regions/{slug}/activities', [WorldMapController::class, 'regionActivities'], [AuthMiddleware::class]);
$router->get('/api/world-map/locations/{slug}', [WorldMapController::class, 'location'], [AuthMiddleware::class]);
$router->get('/api/world-map/locations/{slug}/activities', [WorldMapController::class, 'locationActivities'], [AuthMiddleware::class]);
$router->post('/api/world-map/locations/{slug}/explore', [WorldMapController::class, 'exploreLocation'], [AuthMiddleware::class]);
$router->get('/api/world-map/current-location', [WorldMapController::class, 'currentLocation'], [AuthMiddleware::class]);
$router->post('/api/world-map/travel', [WorldMapController::class, 'travel'], [AuthMiddleware::class]);
$router->post('/api/world-map/travel-and-explore', [WorldMapController::class, 'travelAndExplore'], [AuthMiddleware::class]);
$router->get('/api/world-map/travel-history', [WorldMapController::class, 'travelHistory'], [AuthMiddleware::class]);
$router->get('/api/world-map/territories', [WorldMapController::class, 'territories'], [AuthMiddleware::class]);
$router->get('/api/admin/world-map', [WorldMapController::class, 'adminOverview'], [AuthMiddleware::class]);
$router->get('/api/admin/world-map/regions', [WorldMapController::class, 'adminRegions'], [AuthMiddleware::class]);
$router->get('/api/admin/world-map/locations', [WorldMapController::class, 'adminLocations'], [AuthMiddleware::class]);

$router->get('/api/heat', [HeatController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/heat/logs', [HeatController::class, 'logs'], [AuthMiddleware::class]);
$router->get('/api/heat/reduction-options', [HeatController::class, 'reductionOptions'], [AuthMiddleware::class]);
$router->post('/api/heat/reduce', [HeatController::class, 'reduce'], [AuthMiddleware::class]);
$router->post('/api/heat/process-day', [HeatController::class, 'processDaily'], [AuthMiddleware::class]);
$router->post(
    '/api/heat/lie-low',
    [HeatController::class, 'layLow'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/heat/lay-low',
    [HeatController::class, 'layLow'],
    [AuthMiddleware::class]
);

$router->get('/api/investigations', [InvestigationController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/investigations/{id}', [InvestigationController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/investigations/{id}/respond', [InvestigationController::class, 'respond'], [AuthMiddleware::class]);

$router->get('/api/boss', [BossController::class, 'show'], [AuthMiddleware::class]);
$router->get('/api/boss/history', [BossController::class, 'history'], [AuthMiddleware::class]);
$router->get('/api/boss/succession', [BossController::class, 'succession'], [AuthMiddleware::class]);
$router->post('/api/boss/rename', [BossController::class, 'rename'], [AuthMiddleware::class]);

$router->get('/api/update-notices/pending', [UpdateNoticeController::class, 'pending'], [AuthMiddleware::class]);
$router->post('/api/update-notices/acknowledge', [UpdateNoticeController::class, 'acknowledge'], [AuthMiddleware::class]);

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

$router->post(
    '/api/crime-locations/{code}/explore',
    [CrimeController::class, 'explore'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/crime-opportunities/{id}',
    [CrimeController::class, 'showOpportunity'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/crime-opportunities/{id}/investigate',
    [CrimeController::class, 'investigate'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/crime-opportunities/{id}/prepare',
    [CrimeController::class, 'prepare'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/crime-opportunities/{id}/assign-crew',
    [CrimeController::class, 'assignCrew'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/crime-opportunities/{id}/assign-equipment',
    [CrimeController::class, 'assignEquipment'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/crime-opportunities/{id}/start',
    [CrimeController::class, 'start'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/crime-opportunities/{id}/abandon',
    [CrimeController::class, 'abandon'],
    [AuthMiddleware::class]
);
$router->post(
    '/api/crime-runs/{id}/decision',
    [CrimeController::class, 'decide'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/npc-contacts',
    [CrimeController::class, 'contacts'],
    [AuthMiddleware::class]
);


$router->get('/api/quick-crimes', [QuickCrimeController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/quick-crimes/history', [QuickCrimeController::class, 'history'], [AuthMiddleware::class]);
$router->get('/api/player/progression', [QuickCrimeController::class, 'progression'], [AuthMiddleware::class]);
$router->get('/api/quick-crimes/runs/{id}', [QuickCrimeController::class, 'run'], [AuthMiddleware::class]);
$router->post('/api/quick-crimes/runs/{id}/decision', [QuickCrimeController::class, 'decide'], [AuthMiddleware::class]);
$router->post('/api/quick-crimes/runs/{id}/resolve', [QuickCrimeController::class, 'resolve'], [AuthMiddleware::class]);
$router->get('/api/quick-crimes/{id}', [QuickCrimeController::class, 'show'], [AuthMiddleware::class]);
$router->post('/api/quick-crimes/{id}/prepare', [QuickCrimeController::class, 'prepare'], [AuthMiddleware::class]);
$router->post('/api/quick-crimes/{id}/start', [QuickCrimeController::class, 'start'], [AuthMiddleware::class]);

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
    '/api/admin/npcs',
    [AdminController::class, 'npcs'],
    [AuthMiddleware::class]
);
$router->get(
    '/api/admin/npcs/{id}',
    [AdminController::class, 'npcDetail'],
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
    '/api/admin/users/{id}/heat/clear',
    [AdminController::class, 'clearHeat'],
    [AuthMiddleware::class]
);

$router->post(
    '/api/admin/users/{id}/grant-asset',
    [AdminController::class, 'grantAsset'],
    [AuthMiddleware::class]
);


$router->get('/api/admin/heat', [AdminController::class, 'heat'], [AuthMiddleware::class]);
$router->get('/api/admin/investigations', [AdminController::class, 'investigations'], [AuthMiddleware::class]);
$router->get('/api/admin/characters/{type}/{id}/heat', [AdminController::class, 'characterHeat'], [AuthMiddleware::class]);

$router->get(
    '/api/admin/economy',
    [EconomyController::class, 'status'],
    [AuthMiddleware::class]
);
