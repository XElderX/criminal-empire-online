<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\App;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

App::boot(dirname(__DIR__));
$router = new Router();
require dirname(__DIR__) . '/routes/api.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
