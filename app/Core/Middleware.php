<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

abstract class Middleware
{
    /**
     * Middleware aliases mapped to their implementation classes.
     *
     * @var array<string, class-string<Middleware>>
     */
    private static array $aliases = [
        'auth' => \App\Http\Middleware\Jwt::class,
    ];

    /**
     * Register or replace a middleware alias.
     *
     * @param class-string<Middleware> $class
     */
    public static function alias(string $name, string $class): void
    {
        if (!is_subclass_of($class, self::class)) {
            throw new RuntimeException(
                "Middleware must extend " . self::class . ": {$class}"
            );
        }

        self::$aliases[$name] = $class;
    }

    /**
     * Execute the middleware pipeline.
     *
     * @param array<int, Middleware|string> $middleware
     * @param array<string, string> $parameters
     */
    public static function run(
        array $middleware,
        callable $destination,
        array $parameters = []
    ): mixed {
        foreach (array_reverse($middleware) as $declaration) {
            $next = $destination;

            $destination = function () use (
                $declaration,
                $next,
                $parameters
            ): mixed {
                $arguments = [];

                if ($declaration instanceof self) {
                    $instance = $declaration;
                } else {
                    [$name, $arguments] = self::parse(
                        $declaration
                    );

                    $class = self::$aliases[$name] ?? $name;

                    if (!class_exists($class)) {
                        throw new RuntimeException(
                            "Middleware alias or class not found: {$name}"
                        );
                    }

                    if (!is_subclass_of($class, self::class)) {
                        throw new RuntimeException(
                            "Middleware must extend " . self::class . ": {$class}"
                        );
                    }

                    $instance = new $class();
                }

                $context = $parameters;
                $context['_middleware'] = $arguments;

                return $instance->handle($next, $context);
            };
        }

        return $destination();
    }

    /**
     * Parse declarations such as:
     * auth
     * role:admin,superadmin
     *
     * @return array{string, array<int, string>}
     */
    private static function parse(string $declaration): array
    {
        $parts = explode(':', $declaration, 2);

        $name = trim($parts[0]);
        $argumentString = $parts[1] ?? '';

        if ($name === '') {
            throw new RuntimeException(
                'Middleware declaration cannot be empty.'
            );
        }

        $arguments = $argumentString === ''
            ? []
            : array_map('trim', explode(',', $argumentString));

        return [$name, $arguments];
    }

    /**
     * Middleware implementations must provide their own behavior.
     *
     * @param array<string, mixed> $parameters
     */
    abstract public function handle(
        callable $next,
        array $parameters = []
    ): mixed;
}
