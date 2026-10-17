<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'username', 'email', 'password', 'first_name', 'last_name',
        'role', 'status', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function searchableFields(): array
    {
        return ['username', 'email', 'first_name', 'last_name', 'role', 'status'];
    }

    public static function tableAlias(): string
    {
        return 'users';
    }
}
