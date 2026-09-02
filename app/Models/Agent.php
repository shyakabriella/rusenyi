<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'agent_code',
        'user_id',
        'home_village_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deactivated_by',
        'deactivated_at',
        'reactivated_by',
        'reactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'deactivated_at' => 'datetime',
            'reactivated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function homeVillage(): BelongsTo
    {
        return $this->belongsTo(
            Village::class,
            'home_village_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'deactivated_by'
        );
    }

    public function reactivator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reactivated_by'
        );
    }

    public function collectionPoints(): BelongsToMany
    {
        return $this->belongsToMany(
            CollectionPoint::class,
            'agent_collection_points'
        );
    }

    public function cashAllocations(): HasMany
    {
        return $this->hasMany(CashAllocation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_ACTIVE
        );
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_INACTIVE
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(AgentWalletTransaction::class);
    }

}
