<?php
namespace App\Controllers;
use App\Core\Database; use App\Core\Response; use App\Middleware\AdminMiddleware;
final class AdminController
{
    public function dashboard(array $params, array $context): void
    {
        AdminMiddleware::ensure($context['user']);
        $pdo=Database::pdo();
        Response::json(['stats'=>[
            'users'=>(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'gangs'=>(int)$pdo->query('SELECT COUNT(*) FROM gangs')->fetchColumn(),
            'territories'=>(int)$pdo->query('SELECT COUNT(*) FROM territories')->fetchColumn(),
            'crime_logs'=>(int)$pdo->query('SELECT COUNT(*) FROM crime_logs')->fetchColumn(),
        ]]);
    }
    public function audit(array $params, array $context): void
    {
        AdminMiddleware::ensure($context['user']);
        Response::json(['data'=>Database::pdo()->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100')->fetchAll()]);
    }
}
