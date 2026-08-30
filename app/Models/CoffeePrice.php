<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoffeePrice extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public const TYPE_CHERRY = 'cherry';
    public const TYPE_PARCHMENT = 'parchment';
    public const TYPE_GREEN_COFFEE = 'green_coffee';

    public const COFFEE_TYPES = [
        self::TYPE_CHERRY,
        self::TYPE_PARCHMENT,
        self::TYPE_GREEN_COFFEE,
    ];

    protected $fillable = [
        'coffee_season_id',
        'code',
        'coffee_type',
        'price_per_kg',
        'currency',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'created_by',
        'activated_by',
        'activated_at',
        'deactivated_by',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'coffee_season_id' => 'integer',

            'price_per_kg' =>
                'decimal:2',

            'effective_from' =>
                'date',

            'effective_to' =>
                'date',

            'activated_at' =>
                'datetime',

            'deactivated_at' =>
                'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(
            CoffeeSeason::class,
            'coffee_season_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'activated_by'
        );
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'deactivated_by'
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_ACTIVE
        );
    }

    public function scopeDraft(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_DRAFT
        );
    }

    public function scopeInactive(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_INACTIVE
        );
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status ===
            self::STATUS_ACTIVE;
    }
}
