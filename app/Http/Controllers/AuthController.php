<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\JwtService;

final class AuthController
{
    public function __construct(
        private JwtService $jwt
    ) {}

    public function login(): void
    {
        $email = filter_var(Request::input('email', ''), FILTER_VALIDATE_EMAIL);
        $password = Request::input('password', '');

        if ($email === false || !is_string($password) || $password === '') {
            Response::json([
                'success' => false,
                'message' => 'Valid email and password are required.',
            ], 422);
            return;
        }

        $user = User::where('email', $email)->first();

        if (!$user || !password_verify($password, $user->password)) {
            Response::json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
            return;
        }

        $user->update([
            'last_login_at' => new \DateTimeImmutable(),
        ]);
        Response::json([
            'success' => true,
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->getAccessTtl(),
            'access_token' => $this->jwt->createAccessToken((string) $user->getKey()),
            'user' => $this->userData($user),
        ]);
    }
    public function me(): void
    {
        $userId = $GLOBALS['auth_user_id'] ?? null;
        $user = $userId !== null ? User::find($userId) : null;

        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
            return;
        }

        Response::json([
            'success' => true,
            'user' => $this->userData($user),
        ]);
    }

    public function logout(): void
    {
        Response::json([
            'success' => true,
            'message' => 'Logged out. Discard the access token.',
        ]);
    }

    public function register(): void
    {
        $username = trim((string) Request::input('username', ''));
        $email = filter_var(Request::input('email', ''), FILTER_VALIDATE_EMAIL);
        $password = Request::input('password', '');
        $firstName = trim((string) Request::input('first_name', ''));
        $lastName = trim((string) Request::input('last_name', ''));

        $errors = [];

        if ($username === '' || strlen($username) > 50) {
            $errors['username'] = 'Username is required and must not exceed 50 characters.';
        } elseif (User::where('username', $username)->exists()) {
            $errors['username'] = 'Username is already taken.';
        }

        if ($email === false) {
            $errors['email'] = 'A valid email address is required.';
        } elseif (User::where('email', $email)->exists()) {
            $errors['email'] = 'Email is already registered.';
        }

        if (!is_string($password) || strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (strlen($firstName) > 100) {
            $errors['first_name'] = 'First name must not exceed 100 characters.';
        }

        if (strlen($lastName) > 100) {
            $errors['last_name'] = 'Last name must not exceed 100 characters.';
        }

        if ($errors !== []) {
            Response::json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
            return;
        }

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'role' => 'user',
            'status' => 'active',
        ]);

        Response::json([
            'success' => true,
            'message' => 'Registration successful.',
            'user' => $this->userData($user),
        ], 201);
    }

    private function userData(User $user): array
    {
        return $user->only([
            'id',
            'username',
            'email',
            'first_name',
            'last_name',
            'role',
            'status',
            'email_verified_at',
            'last_login_at',
            'created_at',
        ]);
    }
}
