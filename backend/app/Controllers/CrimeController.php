<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Services\CrimeService;
use Throwable;

final class CrimeController
{
    public function index(array $params = [], array $context = []): void
    {
        Response::json(['data' => Database::pdo()->query('SELECT * FROM crimes ORDER BY energy_cost ASC')->fetchAll()]);
    }

    public function commit(array $params, array $context): void
    {
        try { Response::json((new CrimeService())->commit($context['user'], (int)$params['id'])); }
        catch (Throwable $e) { Response::json(['message' => $e->getMessage()], 422); }
    }

    public function logs(array $params, array $context): void
    {
        $stmt = Database::pdo()->prepare('SELECT cl.*, c.name AS crime_name FROM crime_logs cl JOIN crimes c ON c.id=cl.crime_id WHERE cl.user_id=? ORDER BY cl.id DESC LIMIT 50');
        $stmt->execute([$context['user']['id']]);
        Response::json(['data' => $stmt->fetchAll()]);
    }
}
