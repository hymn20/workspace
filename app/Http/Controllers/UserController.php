<?php

declare(strict_types=1);

namespace App\Http\Controllers;

final class UserController
{
    public function index(): array
    {
        return [
            'users' => 'ewqewq',
        ];
    }

    public function show(string $id): array
    {
        return [
            'user_id' => $id,
        ];
    }

    public function store(): array
    {
        return [
            'message' => 'User creation endpoint',
        ];
    }

    public function update(string $id): array
    {
        return [
            'message' => 'User update endpoint',
            'user_id' => $id,
        ];
    }

    public function destroy(string $id): array
    {
        return [
            'message' => 'User deletion endpoint',
            'user_id' => $id,
        ];
    }
}
