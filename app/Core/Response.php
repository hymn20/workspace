<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * Send a JSON response.
     */
    public static function json(
        array $data,
        int $status = 200,
        array $headers = []
    ): void {
        http_response_code($status);

        header('Content-Type: application/json; charset=UTF-8');

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Send a successful response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200
    ): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Send an error response.
     */
    public static function error(
        string $message,
        int $status = 400,
        mixed $errors = null
    ): void {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $status);
    }

    /**
     * Send a validation error response.
     */
    public static function validation(array $errors): void
    {
        self::error('Validation failed.', 422, $errors);
    }

    /**
     * Send a 401 response.
     */
    public static function unauthorized(
        string $message = 'Unauthenticated.'
    ): void {
        self::error($message, 401);
    }

    /**
     * Send a 403 response.
     */
    public static function forbidden(
        string $message = 'Forbidden.'
    ): void {
        self::error($message, 403);
    }

    /**
     * Send a 404 response.
     */
    public static function notFound(
        string $message = 'Resource not found.'
    ): void {
        self::error($message, 404);
    }

    /**
     * Redirect to another URL.
     */
    public static function redirect(
        string $url,
        int $status = 302
    ): void {
        header('Location: ' . $url, true, $status);
    }

    /**
     * Send an empty response.
     */
    public static function noContent(): void
    {
        http_response_code(204);
    }
}
