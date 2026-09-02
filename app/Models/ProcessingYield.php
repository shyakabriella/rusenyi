<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingYield extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'yield_code',
        'processing_batch_id',
        'store_inventory_id',
        'source_coffee_lot_id',
        'coffee_season_id',
        'input_quantity_kg',
        'output_quantity_kg',
        'loss_quantity_kg',
        'yield_percentage',
        'loss_percentage',
        'output_coffee_type',
        'output_processing_stage',
        'output_bag_count',
        'yield_date',
        'output_coffee_lot_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'processing_batch_id' => 'integer',
            'store_inventory_id' => 'integer',
            'source_coffee_lot_id' => 'integer',
            'coffee_season_id' => 'integer',

            'input_quantity_kg' => 'decimal:2',
            'output_quantity_kg' => 'decimal:2',
            'loss_quantity_kg' => 'decimal:2',

            'yield_percentage' => 'decimal:4',
            'loss_percentage' => 'decimal:4',

            'output_bag_count' => 'integer',

            'yield_date' => 'date',
            'output_coffee_lot_id' => 'integer',

            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $yieldRecord) {
            if ($yieldRecord->yield_code) {
                return;
            }

            $yieldRecord->forceFill([
                'yield_code' => sprintf(
                    'YLD-%06d',
                    $yieldRecord->id
                ),
            ])->saveQuietly();
        });
    }

    public function processingBatch(): BelongsTo
    {
        return $this->belongsTo(
            ProcessingBatch::class
        );
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(
            StoreInventory::class,
            'store_inventory_id'
        );
    }

    public function sourceCoffeeLot(): BelongsTo
    {
        return $this->belongsTo(
            CoffeeLot::class,
            'source_coffee_lot_id'
        );
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(
            CoffeeSeason::class,
            'coffee_season_id'
        );
    }

    public function outputCoffeeLot(): BelongsTo
    {
        return $this->belongsTo(
            CoffeeLot::class,
            'output_coffee_lot_id'
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

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'confirmed_by'
        );
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }
}
