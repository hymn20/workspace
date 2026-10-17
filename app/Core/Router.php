<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

final class Router
{
    /** @var array<int, array{method: string, path: string, handler: mixed, middleware: array<int, Middleware|string>}> */
    private array $routes = [];

    private string $groupPrefix = '';
    private array $groupMiddleware = [];
    private array $middlewareTargets = [];

    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function addRoute(string $method, string $path, mixed $handler, array $middleware = []): self
    {
        if (!is_string($handler) && !is_callable($handler) && !is_array($handler)) {
            throw new RuntimeException('Route handler must be a view path, callable, or controller action.');
        }

        $index = count($this->routes);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->joinPaths($this->groupPrefix, $path),
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];

        $this->middlewareTargets = [$index];

        return $this;
    }

    public function middleware(Middleware|string ...$middleware): self
    {
        if ($this->middlewareTargets === []) {
            throw new RuntimeException('Middleware must be attached to a registered route or group.');
        }

        foreach ($this->middlewareTargets as $index) {
            foreach ($middleware as $item) {
                $this->routes[$index]['middleware'][] = $item;
            }
        }

        return $this;
    }

    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        $startIndex = count($this->routes);

        $this->groupPrefix = $this->joinPaths($this->groupPrefix, $prefix);
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        try {
            $callback($this);
        } finally {
            $this->groupPrefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }

        $endIndex = count($this->routes);
        $this->middlewareTargets = $endIndex > $startIndex
            ? range($startIndex, $endIndex - 1)
            : [];

        return $this;
    }

    public function dispatch(): void
    {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = parse_url($requestUri, PHP_URL_PATH);

        if (!is_string($requestPath)) {
            $this->notFound();
            return;
        }

        $basePath = trim((string) ($_ENV['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH') ?: ''), '/');

        if ($basePath !== '') {
            $basePath = '/' . $basePath;

            if ($requestPath === $basePath) {
                $requestPath = '/';
            } elseif (str_starts_with($requestPath, $basePath . '/')) {
                $requestPath = substr($requestPath, strlen($basePath));
            }
        }

        $requestPath = $this->normalizePath($requestPath);
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $parameters = $this->matchPath($route['path'], $requestPath);

            if ($parameters === null) {
                continue;
            }

            $allowedMethods[] = $route['method'];

            $methodMatches = $route['method'] === $requestMethod
                || ($requestMethod === 'HEAD' && $route['method'] === 'GET');

            if (!$methodMatches) {
                continue;
            }

            $result = Middleware::run(
                $route['middleware'],
                fn(): mixed => $this->executeHandler($route['handler'], $parameters),
                $parameters
            );

            $this->sendResponse($result);
            return;
        }

        if ($allowedMethods !== []) {
            $methods = array_values(array_unique($allowedMethods));

            if (!in_array('OPTIONS', $methods, true)) {
                $methods[] = 'OPTIONS';
            }

            header('Allow: ' . implode(', ', $methods));
            http_response_code(405);
            echo '405 - Method Not Allowed';
            return;
        }

        // No registered route matched: never resolve a view automatically.
        $this->notFound();
    }

    private function renderView(string $view): void
    {
        $viewsDirectory = realpath(BASE_PATH . '/views');

        if ($viewsDirectory === false) {
            $this->notFound();
            return;
        }

        $view = trim($view, '/');

        if (
            $view === ''
            || str_contains($view, '..')
            || str_contains($view, '\\')
            || !preg_match('/^[a-zA-Z0-9_\/-]+$/', $view)
        ) {
            $this->notFound();
            return;
        }

        $viewPath = realpath($viewsDirectory . DIRECTORY_SEPARATOR . $view . '.php');

        if (
            $viewPath === false
            || !is_file($viewPath)
            || !str_starts_with($viewPath, $viewsDirectory . DIRECTORY_SEPARATOR)
        ) {
            $this->notFound();
            return;
        }

        require $viewPath;
    }

    /**
     * Execute a view, closure, or controller action.
     *
     * @param array<string, string> $parameters
     */
    private function executeHandler(mixed $handler, array $parameters): mixed
    {
        if (is_string($handler)) {
            $this->renderView($handler);
            return null;
        }

        if (is_array($handler)) {
            if (count($handler) !== 2) {
                throw new RuntimeException('Controller action must contain a class and method.');
            }

            [$controller, $method] = $handler;

            if (is_string($controller)) {
                $controller = $this->resolveController($controller);
            }

            if (!is_object($controller) || !is_string($method)) {
                throw new RuntimeException('Invalid controller action.');
            }

            if (!method_exists($controller, $method)) {
                throw new RuntimeException('Controller method not found.');
            }

            $reflection = new ReflectionMethod($controller, $method);

            if (!$reflection->isPublic()) {
                throw new RuntimeException('Controller method must be public.');
            }

            return $controller->{$method}(...array_values($parameters));
        }

        return $handler(...array_values($parameters));
    }

    private function resolveController(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Controller class not found: {$class}");
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Controller cannot be instantiated: {$class}");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencyClass = $type->getName();

                if ($dependencyClass === PDO::class) {
                    $dependencies[] = Database::connection();
                    continue;
                }

                if (class_exists($dependencyClass)) {
                    $dependencies[] = $this->resolveController($dependencyClass);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(sprintf(
                'Cannot resolve parameter $%s in %s::__construct().',
                $parameter->getName(),
                $class
            ));
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    private function sendResponse(mixed $result): void
    {
        if ($result === null) {
            return;
        }

        if (is_string($result) || is_numeric($result)) {
            echo (string) $result;
            return;
        }

        if (is_array($result) || is_object($result)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }

            echo json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            return;
        }

        throw new RuntimeException('Unsupported route handler response.');
    }

    /**
     * Match a route and extract its dynamic parameters.
     *
     * @return array<string, string>|null
     */
    private function matchPath(string $routePath, string $requestPath): ?array
    {
        $routeSegments = $this->getSegments($routePath);
        $requestSegments = $this->getSegments($requestPath);

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $parameters = [];

        foreach ($routeSegments as $index => $routeSegment) {
            $requestSegment = $requestSegments[$index];

            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $routeSegment, $matches)) {
                $parameters[$matches[1]] = rawurldecode($requestSegment);
                continue;
            }

            if ($routeSegment !== $requestSegment) {
                return null;
            }
        }

        return $parameters;
    }

    private function joinPaths(string $prefix, string $path): string
    {
        if ($prefix === '') {
            return $this->normalizePath($path);
        }

        return $this->normalizePath('/' . trim($prefix, '/') . '/' . trim($path, '/'));
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** @return list<string> */
    private function getSegments(string $path): array
    {
        return $path === '/' ? [] : explode('/', trim($path, '/'));
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo '404 - Page Not Found';
    }
}
