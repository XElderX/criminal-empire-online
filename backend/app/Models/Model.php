<?php
namespace App\Models;

use App\Core\Database;

abstract class Model
{
    protected static string $table;

    public static function find(int|string $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ' . static::$table . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM ' . static::$table . ' ORDER BY id DESC')->fetchAll();
    }
}
