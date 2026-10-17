<?php

declare(strict_types=1);

namespace App\Core;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static array $configurations = [];
    private static array $connections = [];
    private static ?Capsule $capsule = null;

    private function __construct() {}

    public static function configureFromEnvironment(): void
    {
        $names = array_unique(array_filter(array_map('trim', explode(',', self::env('DB_CONNECTIONS', 'default')))));
        if (!in_array('default', $names, true)) $names[] = 'default';

        foreach ($names as $name) {
            if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $name)) throw new RuntimeException("Invalid database connection name: {$name}");

            $prefix = $name === 'default' ? 'DB' : 'DB_' . strtoupper($name);
            $driver = self::env("{$prefix}_DRIVER", 'mysql');

            self::addConnection($name, [
                'driver' => $driver,
                'host' => self::env("{$prefix}_HOST", '127.0.0.1'),
                'port' => self::env("{$prefix}_PORT", $driver === 'pgsql' ? '5432' : '3306'),
                'database' => self::env("{$prefix}_DATABASE", ''),
                'username' => self::env("{$prefix}_USERNAME", 'root'),
                'password' => self::env("{$prefix}_PASSWORD", ''),
                'charset' => self::env("{$prefix}_CHARSET", 'utf8mb4'),
                'collation' => self::env("{$prefix}_COLLATION", 'utf8mb4_unicode_ci'),
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'foreign_key_constraints' => true,
                'options' => [],
            ]);
        }

        self::bootEloquent();
    }

    public static function addConnection(string $name, array $config): void
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $name)) throw new RuntimeException("Invalid database connection name: {$name}");

        self::disconnect($name);
        self::$configurations[$name] = $config;

        if (self::$capsule !== null) {
            self::registerEloquentConnection($name, $config);
        }
    }

    public static function bootEloquent(): void
    {
        if (self::$capsule === null) {
            self::$capsule = new Capsule();
        }

        foreach (self::$configurations as $name => $config) {
            self::registerEloquentConnection($name, $config);
        }

        self::$capsule->getDatabaseManager()->setDefaultConnection('default');
        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();

        Facade::setFacadeApplication(self::$capsule->getContainer());
    }

    private static function registerEloquentConnection(string $name, array $config): void
    {
        self::$capsule->addConnection($config, $name);
        self::$capsule->getDatabaseManager()->purge($name);
    }

    public static function eloquent(): Capsule
    {
        if (self::$capsule === null) self::bootEloquent();

        return self::$capsule;
    }

    public static function connection(string $name = 'default'): PDO
    {
        if (isset(self::$connections[$name])) return self::$connections[$name];

        $config = self::$configurations[$name] ?? throw new RuntimeException("Database connection [{$name}] is not configured.");
        $driver = strtolower((string) ($config['driver'] ?? 'mysql'));
        $database = (string) ($config['database'] ?? '');

        if ($database === '') throw new RuntimeException("Database name is missing for connection [{$name}].");

        $dsn = match ($driver) {
            'mysql' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? '3306', $database, $config['charset'] ?? 'utf8mb4'),
            'pgsql' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? '5432', $database),
            'sqlite' => 'sqlite:' . $database,
            default => throw new RuntimeException("Unsupported database driver [{$driver}]."),
        };

        $options = $config['options'] ?? [];
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $options[PDO::ATTR_EMULATE_PREPARES] = false;

        try {
            return self::$connections[$name] = new PDO(
                $dsn,
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? ''),
                $options
            );
        } catch (PDOException $exception) {
            error_log("Database connection [{$name}] failed: " . $exception->getMessage());
            throw new RuntimeException("Unable to connect to database [{$name}].", 0, $exception);
        }
    }

    public static function disconnect(string $name = 'default'): void
    {
        unset(self::$connections[$name]);

        if (self::$capsule !== null) {
            self::$capsule->getDatabaseManager()->purge($name);
        }
    }

    public static function disconnectAll(): void
    {
        self::$connections = [];

        if (self::$capsule !== null) {
            foreach (array_keys(self::$configurations) as $name) {
                self::$capsule->getDatabaseManager()->purge($name);
            }
        }
    }

    public static function connectionNames(): array
    {
        return array_keys(self::$configurations);
    }

    private static function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return $value === false || $value === null ? $default : (string) $value;
    }
}
