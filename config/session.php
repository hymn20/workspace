<?php

declare(strict_types=1);

return [
    'name' => '__Host-WorkspaceSession',

    'lifetime' => 120,

    'cookie_httponly' => true,
    'cookie_secure' => true, // Production over HTTPS
    'cookie_samesite' => 'Lax',
    'cookie_path' => '/',

    'cookie_domain' => null,
];