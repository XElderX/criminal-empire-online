<?php
namespace App\Core;

final class App
{
    public static string $basePath;
    public static array $env = [];

    public static function boot(string $basePath): void
    {
        self::$basePath = $basePath;
        self::loadEnv($basePath . '/.env');
        self::cors();
    }

    private static function loadEnv(string $file): void
    {
        if (!is_file($file)) {
            $file = self::$basePath . '/.env.example';
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            self::$env[trim($key)] = trim($value);
        }
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        return self::$env[$key] ?? getenv($key) ?: $default;
    }

    private static function cors(): void
    {
        $origin = self::env('CORS_ALLOWED_ORIGIN', '*');
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
