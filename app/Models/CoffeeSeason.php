<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoffeeSeason extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'name',
        'code',
        'start_date',
        'end_date',
        'status',
        'description',
        'created_by',
        'activated_by',
        'activated_at',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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

    public function closer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by'
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

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_ACTIVE
        );
    }

    public function scopeClosed(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_CLOSED
        );
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status ===
            self::STATUS_ACTIVE;
    }

    public function coffeePrices(): HasMany
    {
        return $this->hasMany(
            CoffeePrice::class,
            'coffee_season_id'
        );
    }

}
