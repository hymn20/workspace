<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Services\JwtService;
use Throwable;

final class Jwt extends Middleware
{
    public function handle(
        callable $next,
        array $parameters = []
    ): mixed {
        $token = $this->getBearerToken();

        if ($token === null) {
            return $this->unauthorized(
                'Authentication token is required.'
            );
        }

        try {
            $jwt = new JwtService();
            $payload = $jwt->verify($token);

            // Controllers can use this to identify the authenticated user.
            $GLOBALS['auth_user_id'] = $payload->sub;
            $GLOBALS['auth_token_payload'] = $payload;

        } catch (Throwable $exception) {
            return $this->unauthorized(
                'Invalid or expired authentication token.'
            );
        }

        return $next();
    }

 private function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();

        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    if (
        !is_string($header) ||
        !preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches)
    ) {
        return null;
    }

    return $matches[1];
}

    private function unauthorized(string $message): null
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'success' => false,
            'message' => $message,
        ]);

        return null;
    }
}
