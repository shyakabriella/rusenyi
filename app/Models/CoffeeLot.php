<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoffeeLot extends Model
{
    public const SOURCE_FACTORY_RECEPTION =
        'factory_reception';

    public const SOURCE_DIRECT_FARMER_DELIVERY =
        'direct_farmer_delivery';

    public const SOURCE_PROCESSING_BATCH =
        'processing_batch';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    public const STAGE_RECEIVED = 'received';
    public const STAGE_STORED = 'stored';
    public const STAGE_PROCESSING = 'processing';
    public const STAGE_PROCESSED = 'processed';

    protected $fillable = [
        'lot_code',
        'coffee_season_id',
        'source_type',
        'source_id',
        'coffee_type',
        'initial_weight_kg',
        'current_weight_kg',
        'bag_count',
        'processing_stage',
        'status',
        'storage_location',
        'lot_date',
        'notes',
        'created_by',
        'updated_by',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'coffee_season_id' => 'integer',
            'source_id' => 'integer',
            'initial_weight_kg' => 'decimal:2',
            'current_weight_kg' => 'decimal:2',
            'bag_count' => 'integer',
            'lot_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $lot) {
            if ($lot->lot_code) {
                return;
            }

            $lot->forceFill([
                'lot_code' => sprintf(
                    'LOT-%06d',
                    $lot->id
                ),
            ])->saveQuietly();
        });
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by'
        );
    }

    public function storeInventories(): HasMany
    {
        return $this->hasMany(
            StoreInventory::class
        );
    }


    public function processingBatches(): HasMany
    {
        return $this->hasMany(
            ProcessingBatch::class
        );
    }

}
