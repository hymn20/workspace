<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?array $parsedBody = null;

    /**
     * Get all request input, with body values taking precedence
     * over query parameters.
     */
    public static function all(): array
    {
        return array_replace(
            self::query(),
            self::body()
        );
    }

    /**
     * Get one input value or all input values.
     */
    public static function input(?string $key = null, mixed $default = null): mixed
    {
        $data = self::all();

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Get only the requested input keys.
     */
    public static function only(array $keys): array
    {
        return array_intersect_key(
            self::all(),
            array_flip($keys)
        );
    }

    /**
     * Exclude the requested input keys.
     */
    public static function except(array $keys): array
    {
        return array_diff_key(
            self::all(),
            array_flip($keys)
        );
    }

    /**
     * Check whether an input key exists, even if its value is null.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * Check whether an input value is present and non-empty.
     */
    public static function filled(string $key): bool
    {
        $value = self::input($key);

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * Get query-string parameters.
     */
    public static function query(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        if ($key === null) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }

    /**
     * Get parsed request body.
     */
    public static function body(): array
    {
        if (self::$parsedBody !== null) {
            return self::$parsedBody;
        }

        $contentType = strtolower(
            trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])
        );

        if ($contentType === 'application/json') {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw ?: '', true);

            return self::$parsedBody = is_array($data)
                ? $data
                : [];
        }

        if ($contentType === 'application/x-www-form-urlencoded'
            || $contentType === 'multipart/form-data'
            || $contentType === ''
        ) {
            return self::$parsedBody = $_POST;
        }

        return self::$parsedBody = [];
    }

    /**
     * Get the raw JSON-decoded request body.
     */
    public static function json(): array
    {
        $contentType = strtolower(
            trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])
        );

        if ($contentType !== 'application/json') {
            return [];
        }

        return self::body();
    }

    /**
     * Get uploaded files.
     */
    public static function files(?string $key = null): mixed
    {
        if ($key === null) {
            return $_FILES;
        }

        return $_FILES[$key] ?? null;
    }

    public static function hasFile(string $key): bool
    {
        return isset($_FILES[$key])
            && is_array($_FILES[$key])
            && ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Get request headers.
     */
    public static function headers(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            if (is_array($headers)) {
                return $headers;
            }
        }

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );

                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * Get a header case-insensitively.
     */
    public static function header(
        string $key,
        mixed $default = null
    ): mixed {
        foreach (self::headers() as $name => $value) {
            if (strcasecmp($name, $key) === 0) {
                return $value;
            }
        }

        return $default;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function uri(): string
    {
        return parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        ) ?: '/';
    }

    public static function fullUrl(): string
    {
        $https = !empty($_SERVER['HTTPS'])
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';

        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $scheme . '://' . $host
            . ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    public static function userAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * Extract a Bearer token from the Authorization header.
     */
    public static function bearerToken(): ?string
    {
        $authorization = self::header('Authorization');

        if (!is_string($authorization)
            || !preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches)
        ) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Return the authenticated user set by your authentication middleware.
     */
    public static function user(): mixed
    {
        return $GLOBALS['auth_user'] ?? null;
    }

    public static function server(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        if ($key === null) {
            return $_SERVER;
        }

        return $_SERVER[$key] ?? $default;
    }
}
