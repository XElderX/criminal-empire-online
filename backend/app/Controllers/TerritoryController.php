<?php
namespace App\Controllers;
use App\Core\Database; use App\Core\Response;
final class TerritoryController
{
    public function index(array $params = [], array $context = []): void { Response::json(['data'=>Database::pdo()->query('SELECT t.*, g.name owner_gang FROM territories t LEFT JOIN gangs g ON g.id=t.owner_gang_id ORDER BY t.id')->fetchAll()]); }
}
