<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInventory extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'inventory_code',
        'coffee_lot_id',
        'coffee_season_id',
        'source_type',
        'source_id',
        'coffee_type',
        'initial_quantity_kg',
        'current_quantity_kg',
        'bag_count',
        'storage_location',
        'received_at',
        'status',
        'notes',
        'received_by',
        'created_by',
        'updated_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'coffee_lot_id' => 'integer',
            'coffee_season_id' => 'integer',
            'source_id' => 'integer',

            'initial_quantity_kg' => 'decimal:2',
            'current_quantity_kg' => 'decimal:2',

            'bag_count' => 'integer',

            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $inventory) {
            if ($inventory->inventory_code) {
                return;
            }

            $inventory->forceFill([
                'inventory_code' => sprintf(
                    'INV-%06d',
                    $inventory->id
                ),
            ])->saveQuietly();
        });
    }

    public function coffeeLot(): BelongsTo
    {
        return $this->belongsTo(
            CoffeeLot::class
        );
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(
            CoffeeSeason::class,
            'coffee_season_id'
        );
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'received_by'
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

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(
            StockMovement::class
        );
    }


    public function processingBatches(): HasMany
    {
        return $this->hasMany(
            ProcessingBatch::class
        );
    }

}
