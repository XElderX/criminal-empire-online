<?php
namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $host = App::env('DB_HOST', '127.0.0.1');
        $port = App::env('DB_PORT', '3306');
        $db = App::env('DB_DATABASE', 'criminal_empire');
        $user = App::env('DB_USERNAME', 'root');
        $pass = App::env('DB_PASSWORD', '');
        self::$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
}
