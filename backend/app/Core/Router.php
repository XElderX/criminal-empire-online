<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(
        string $method,
        string $path,
        array $handler,
        array $middleware
    ): void {
        $pattern = '#^'
            . preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path)
            . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $params = array_filter(
                $matches,
                'is_string',
                ARRAY_FILTER_USE_KEY
            );

            $context = [];

            foreach ($route['middleware'] as $middlewareClass) {
                $middlewareContext = (new $middlewareClass())->handle();
                $context = array_merge($context, $middlewareContext);
            }

            [$controllerClass, $action] = $route['handler'];
            (new $controllerClass())->$action($params, $context);

            return;
        }

        Response::json(['message' => 'Not found'], 404);
    }
}
