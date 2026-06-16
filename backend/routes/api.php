<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CrimeController;
use App\Controllers\GangController;
use App\Controllers\MarketController;
use App\Controllers\ShopController;
use App\Controllers\TerritoryController;
use App\Controllers\JobController;
use App\Controllers\RecruitmentController;
use App\Controllers\EconomyController;
use App\Middleware\AuthMiddleware;

$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->get('/api/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

$router->get('/api/crimes', [CrimeController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/crimes/{id}/commit', [CrimeController::class, 'commit'], [AuthMiddleware::class]);
$router->get('/api/crime-logs', [CrimeController::class, 'logs'], [AuthMiddleware::class]);

$router->get('/api/weapons', [ShopController::class, 'weapons'], [AuthMiddleware::class]);
$router->post('/api/weapons/{id}/buy', [ShopController::class, 'buyWeapon'], [AuthMiddleware::class]);
$router->get('/api/inventory', [ShopController::class, 'inventory'], [AuthMiddleware::class]);

$router->get('/api/drug-market', [MarketController::class, 'drugs'], [AuthMiddleware::class]);
$router->get('/api/gangs', [GangController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/gangs', [GangController::class, 'create'], [AuthMiddleware::class]);

$router->get('/api/jobs', [JobController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/jobs/active', [JobController::class, 'active'], [AuthMiddleware::class]);
$router->post('/api/jobs/{id}/start', [JobController::class, 'start'], [AuthMiddleware::class]);
$router->post('/api/job-runs/{id}/complete', [JobController::class, 'complete'], [AuthMiddleware::class]);

$router->get('/api/recruitment', [RecruitmentController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/recruitment/{id}/hire', [RecruitmentController::class, 'hire'], [AuthMiddleware::class]);
$router->get('/api/my-gang', [RecruitmentController::class, 'members'], [AuthMiddleware::class]);
$router->post('/api/my-gang/{id}/pay-overdue', [RecruitmentController::class, 'payOverdue'], [AuthMiddleware::class]);

$router->get('/api/territories', [TerritoryController::class, 'index'], [AuthMiddleware::class]);

$router->get('/api/admin/dashboard', [AdminController::class, 'dashboard'], [AuthMiddleware::class]);
$router->get('/api/admin/audit', [AdminController::class, 'audit'], [AuthMiddleware::class]);
$router->post('/api/admin/users/{id}/energy/refill', [AdminController::class, 'refillEnergy'], [AuthMiddleware::class]);
$router->post('/api/admin/users/{id}/cash/set', [AdminController::class, 'setCash'], [AuthMiddleware::class]);

$router->get('/api/admin/economy', [EconomyController::class, 'status'], [AuthMiddleware::class]);
