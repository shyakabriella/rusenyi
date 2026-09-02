<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_ACCOUNTANT = 'accountant';
    public const ROLE_BALANCE = 'balance';
    public const ROLE_AGENT = 'agent';
    public const ROLE_DRIVER = 'driver';
    public const ROLE_STORE = 'store';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'is_active',
        'must_change_password',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAccountant(): bool
    {
        return $this->role === self::ROLE_ACCOUNTANT;
    }

    public function isBalance(): bool
    {
        return $this->role === self::ROLE_BALANCE;
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    public function isStore(): bool
    {
        return $this->role === self::ROLE_STORE;
    }

    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_ACCOUNTANT,
            self::ROLE_BALANCE,
            self::ROLE_AGENT,
            self::ROLE_DRIVER,
            self::ROLE_STORE,
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(
            new ResetPasswordNotification($token)
        );
    }

    public function collectionPoints(): BelongsToMany
    {
        return $this->belongsToMany(
            CollectionPoint::class,
            'agent_collection_points',
            'user_id',
            'collection_point_id'
        )
            ->withPivot([
                'is_active',
                'assigned_at',
                'unassigned_at',
            ])
            ->withTimestamps();
    }


    public function agentProfile(): HasOne
    {
        return $this->hasOne(
            Agent::class
        );
    }

}
