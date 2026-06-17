<?php

namespace App\Core;

final class App
{
    public static string $basePath;

    /** @var array<string, string> */
    public static array $env = [];

    public static function boot(string $basePath): void
    {
        self::$basePath = $basePath;
        self::loadEnv($basePath . '/.env');
        self::configureCors();
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$env)) {
            return self::$env[$key];
        }

        $environmentValue = getenv($key);

        if ($environmentValue !== false) {
            return $environmentValue;
        }

        return $default;
    }

    private static function loadEnv(string $file): void
    {
        if (!is_file($file)) {
            $file = self::$basePath . '/.env.example';
        }

        $lines = file(
            $file,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (
                $trimmedLine === ''
                || str_starts_with($trimmedLine, '#')
                || !str_contains($trimmedLine, '=')
            ) {
                continue;
            }

            [$key, $value] = explode('=', $trimmedLine, 2);
            $key = trim($key);
            $value = trim($value);

            self::$env[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    private static function configureCors(): void
    {
        $origin = (string) self::env('CORS_ALLOWED_ORIGIN', '*');

        header("Access-Control-Allow-Origin: {$origin}");
        header(
            'Access-Control-Allow-Headers: '
            . 'Content-Type, Authorization, X-Requested-With'
        );
        header(
            'Access-Control-Allow-Methods: '
            . 'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );
        header('Access-Control-Allow-Credentials: true');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
