<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_STOCK_IN = 'stock_in';
    public const TYPE_STOCK_OUT = 'stock_out';
    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';
    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';
    public const TYPE_PROCESSING_ISSUE = 'processing_issue';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_REVERSAL = 'reversal';

    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    public const TYPES = [
        self::TYPE_STOCK_IN,
        self::TYPE_STOCK_OUT,
        self::TYPE_ADJUSTMENT_IN,
        self::TYPE_ADJUSTMENT_OUT,
        self::TYPE_PROCESSING_ISSUE,
        self::TYPE_TRANSFER,
    ];

    public const MANUAL_TYPES = [
        self::TYPE_STOCK_IN,
        self::TYPE_STOCK_OUT,
        self::TYPE_ADJUSTMENT_IN,
        self::TYPE_ADJUSTMENT_OUT,
        self::TYPE_TRANSFER,
    ];

    public const IN_TYPES = [
        self::TYPE_STOCK_IN,
        self::TYPE_ADJUSTMENT_IN,
    ];

    public const OUT_TYPES = [
        self::TYPE_STOCK_OUT,
        self::TYPE_ADJUSTMENT_OUT,
        self::TYPE_PROCESSING_ISSUE,
    ];

    protected $fillable = [
        'movement_code',
        'store_inventory_id',
        'coffee_lot_id',
        'coffee_season_id',
        'movement_type',
        'quantity_kg',
        'quantity_before_kg',
        'quantity_after_kg',
        'from_location',
        'to_location',
        'reference_type',
        'reference_id',
        'reverses_stock_movement_id',
        'reason',
        'notes',
        'status',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'store_inventory_id' => 'integer',
            'coffee_lot_id' => 'integer',
            'coffee_season_id' => 'integer',

            'quantity_kg' => 'decimal:2',
            'quantity_before_kg' => 'decimal:2',
            'quantity_after_kg' => 'decimal:2',

            'reference_id' => 'integer',
            'reverses_stock_movement_id' => 'integer',

            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $movement) {
            if ($movement->movement_code) {
                return;
            }

            $movement->forceFill([
                'movement_code' => sprintf(
                    'SM-%06d',
                    $movement->id
                ),
            ])->saveQuietly();
        });
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(
            StoreInventory::class,
            'store_inventory_id'
        );
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

    public function poster(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'posted_by'
        );
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reversed_by'
        );
    }

    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'reverses_stock_movement_id'
        );
    }
}
