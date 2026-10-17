<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;
use App\Core\Router;
final class Bootstrap
{
    private static bool $booted = false;

    /**
     * Initialize and run the application.
     */
    public static function run(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        self::configureErrorHandling();
        self::loadEnvironment();
        self::loadConfiguration();

        self::dispatchRequest();
    }

    /**
     * Configure PHP error reporting.
     */
    private static function configureErrorHandling(): void
    {
        error_reporting(E_ALL);


        set_exception_handler(
            static function (Throwable $exception): void {
                error_log((string) $exception);

                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');

                $isProduction = strtolower(
                    (string) (
                        $_ENV['APP_ENV']
                        ?? getenv('APP_ENV')
                        ?: 'production'
                    )
                ) === 'production';

                if ($isProduction) {
                    echo 'Internal Server Error';
                    return;
                }

                echo (string) $exception;
            }
        );
    }

    /**
     * Load environment variables from .env.
     *
     * Requires vlucas/phpdotenv.
     */
    private static function loadEnvironment(): void
    {
        $dotenvPath = BASE_PATH . '/vendor/autoload.php';

        if (!class_exists(\Dotenv\Dotenv::class)) {
            return;
        }

        $envFile = BASE_PATH . '/.env';

        if (is_file($envFile)) {
            \Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
        }
    }

    /**
     * Load PHP configuration files.
     */
    private static function loadConfiguration(): void
    {
        $configDirectory = BASE_PATH . '/config';

        if (!is_dir($configDirectory)) {
            return;
        }

        foreach (glob($configDirectory . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');

            $config[$key] = require $file;
        }

        // Store configuration only if your framework has a config service.
        // Example: Config::load($config ?? []);
    }

    /**
     * Dispatch the current request.
     */


    private static function dispatchRequest(): void
    {
        $router = new Router();

        $webRoutes = BASE_PATH . '/routes/web.php';
        $apiRoutes = BASE_PATH . '/routes/api.php';

        if (is_file($webRoutes)) {
            require $webRoutes;
        }

        if (is_file($apiRoutes)) {
            require $apiRoutes;
        }

        $router->dispatch();
    }



    /**
     * Determine whether the application is in production.
     */
    private static function isProduction(): bool
    {
        $environment = $_ENV['APP_ENV']
            ?? getenv('APP_ENV')
            ?: 'production';

        return strtolower((string) $environment) === 'production';
    }
}
