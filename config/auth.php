<?php

declare(strict_types=1);

return [
    'guard' => 'jwt',

    'issuer' => $_ENV['APP_URL'] ?? 'http://localhost',

    // Short-lived access token: 15 minutes.
    'access_ttl' => 900,

    // Refresh token lifetime: 7 days.
    'refresh_ttl' => 604800,

    // Generate a unique secret for each environment.
    'secret' => $_ENV['JWT_SECRET'] ?? '',
];
