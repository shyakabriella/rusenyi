<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingBatch extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'batch_code',
        'store_inventory_id',
        'coffee_lot_id',
        'coffee_season_id',
        'process_name',
        'input_quantity_kg',
        'source_storage_location',
        'planned_start_at',
        'status',
        'processing_issue_movement_id',
        'notes',
        'created_by',
        'updated_by',
        'started_by',
        'started_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'store_inventory_id' => 'integer',
            'coffee_lot_id' => 'integer',
            'coffee_season_id' => 'integer',

            'input_quantity_kg' => 'decimal:2',

            'processing_issue_movement_id' =>
                'integer',

            'planned_start_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $batch) {
            if ($batch->batch_code) {
                return;
            }

            $batch->forceFill([
                'batch_code' => sprintf(
                    'PROC-%06d',
                    $batch->id
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

    public function processingIssueMovement(): BelongsTo
    {
        return $this->belongsTo(
            StockMovement::class,
            'processing_issue_movement_id'
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

    public function starter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'started_by'
        );
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'completed_by'
        );
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function yieldRecords(): HasMany
    {
        return $this->hasMany(
            ProcessingYield::class
        );
    }

}
