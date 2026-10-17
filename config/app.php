<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'PHP MVC App',

    'env' => $_ENV['APP_ENV'] ?? 'production',

    'url' => $_ENV['APP_URL'] ?? 'http://localhost',

    'base_path' => trim(
        (string) ($_ENV['APP_BASE_PATH'] ?? ''),
        '/'
    ),

    'debug' => filter_var(
        $_ENV['APP_DEBUG'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    ),

    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Manila',
];
