<?php

require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\App;
use App\Services\EconomyStatusService;

App::boot(dirname(__DIR__));

$report = (new EconomyStatusService())->report();

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo PHP_EOL;
