<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

/** @var Router $router */
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/register', [AuthController::class, 'register']);

$router->get('/api/me', [AuthController::class, 'me'])->middleware('auth');

$router->post('/api/logout', [AuthController::class, 'logout'])->middleware('auth');
// User API
$router->get('/api/users', [UserController::class, 'index']);
$router->get('/api/users/{id}', [UserController::class, 'show']);

$router->post('/api/users', [UserController::class, 'store']);
$router->put('/api/users/{id}', [UserController::class, 'update']);
$router->delete('/api/users/{id}', [UserController::class, 'destroy']);

// API health check
$router->get('/api/health', static function (): array {
    return ['status' => 'ok'];
});
