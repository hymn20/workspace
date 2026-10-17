<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

final class JwtService
{
    private string $secret;
    private string $issuer;
    private int $accessTtl;

    public function __construct()
    {
        $this->secret = (string) (
            $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: ''
        );

        $this->issuer = (string) (
            $_ENV['JWT_ISSUER'] ?? getenv('JWT_ISSUER') ?: 'http://localhost'
        );

        $this->accessTtl = (int) (
            $_ENV['JWT_ACCESS_TTL']
            ?? getenv('JWT_ACCESS_TTL')
            ?: 900
        );

        if (strlen($this->secret) < 32) {
            throw new \RuntimeException(
                'JWT_SECRET must contain at least 32 characters.'
            );
        }
    }

    public function createAccessToken(
        int|string $userId,
        array $claims = []
    ): string {
        $now = time();

        // Prevent callers from overriding reserved claims.
        unset(
            $claims['iss'],
            $claims['sub'],
            $claims['iat'],
            $claims['nbf'],
            $claims['exp'],
            $claims['jti']
        );

        $payload = array_merge($claims, [
            'iss' => $this->issuer,
            'sub' => (string) $userId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
            'jti' => bin2hex(random_bytes(16)),
        ]);

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function verify(string $token): object
    {
        $payload = JWT::decode(
            $token,
            new Key($this->secret, 'HS256')
        );

        if (
            !isset($payload->iss) ||
            !is_string($payload->iss) ||
            !hash_equals($this->issuer, $payload->iss)
        ) {
            throw new UnexpectedValueException(
                'Invalid token issuer.'
            );
        }

        if (
            !isset($payload->sub) ||
            !is_string($payload->sub) ||
            $payload->sub === ''
        ) {
            throw new UnexpectedValueException(
                'Invalid token subject.'
            );
        }

        return $payload;
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }
}
