<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CrimeController;
use App\Controllers\GangController;
use App\Controllers\MarketController;
use App\Controllers\ShopController;
use App\Controllers\TerritoryController;
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
$router->get('/api/territories', [TerritoryController::class, 'index'], [AuthMiddleware::class]);

$router->get('/api/admin/dashboard', [AdminController::class, 'dashboard'], [AuthMiddleware::class]);
$router->get('/api/admin/audit', [AdminController::class, 'audit'], [AuthMiddleware::class]);
