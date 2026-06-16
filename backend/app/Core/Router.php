<?php
namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, array $handler, array $middleware = []): void { $this->add('POST', $path, $handler, $middleware); }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $pattern = '#^' . preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path) . '$#';
        $this->routes[] = compact('method', 'path', 'pattern', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (!preg_match($route['pattern'], $path, $matches)) continue;
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $context = [];
            foreach ($route['middleware'] as $middleware) {
                $context = array_merge($context, (new $middleware())->handle());
            }
            [$class, $action] = $route['handler'];
            (new $class())->$action($params, $context);
            return;
        }
        Response::json(['message' => 'Not found'], 404);
    }
}
