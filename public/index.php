<?php

declare(strict_types=1);

use App\Core\Bootstrap;
use App\Core\Database;
/*
|--------------------------------------------------------------------------
| Define the application root
|--------------------------------------------------------------------------
*/

define('BASE_PATH', dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| Load Composer autoloader
|--------------------------------------------------------------------------
*/

$autoloadPath = BASE_PATH . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(500);
    exit('Application dependencies are not installed.');
}

require_once $autoloadPath;

/*
|--------------------------------------------------------------------------
| Load environment variables
|--------------------------------------------------------------------------
*/

$envFile = BASE_PATH . '/.env';

if (class_exists(\Dotenv\Dotenv::class) && is_file($envFile)) {
    \Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}

/*
|--------------------------------------------------------------------------
| Configure PHP error reporting
|--------------------------------------------------------------------------
*/

$isProduction = strtolower(
    (string) (
        $_ENV['APP_ENV']
        ?? getenv('APP_ENV')
        ?: 'production'
    )
) === 'production';

error_reporting(E_ALL);

ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');

/*
|--------------------------------------------------------------------------
| Load application bootstrap
|--------------------------------------------------------------------------
*/
Database::configureFromEnvironment();
$bootstrapPath = BASE_PATH . '/app/Core/Bootstrap.php';

if (!is_file($bootstrapPath)) {
    http_response_code(500);
    exit('Application bootstrap file not found.');
}

require_once $bootstrapPath;

/*
|--------------------------------------------------------------------------
| Run application
|--------------------------------------------------------------------------
*/

Bootstrap::run();
