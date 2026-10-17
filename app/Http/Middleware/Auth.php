<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\JwtService;
use Throwable;

final class AuthMiddleware
{
    public function __construct(
        private JwtService $jwt
    ) {}

    public function handle(): bool
    {
        $token = Request::bearerToken();

        if (!$token) {
            Response::json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);

            return false;
        }

        try {
            // Use the method that exists in your JwtService.
            $payload = $this->jwt->verify($token);

            $userId = $payload->sub ?? null;

            if (!is_string($userId) && !is_int($userId)) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid access token.',
                ], 401);

                return false;
            }

            $GLOBALS['auth_user_id'] = $userId;
            $GLOBALS['auth_token_payload'] = $payload;

            return true;
        } catch (Throwable $exception) {
            Response::json([
                'success' => false,
                'message' => 'Invalid or expired access token.',
            ], 401);

            return false;
        }
    }
}
