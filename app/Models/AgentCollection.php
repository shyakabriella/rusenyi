<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentCollection extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'collection_code',
        'coffee_season_id',
        'agent_id',
        'collection_point_id',
        'collection_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'coffee_season_id' => 'integer',
            'agent_id' => 'integer',
            'collection_point_id' => 'integer',
            'collection_date' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $collection) {
            if ($collection->collection_code) {
                return;
            }

            $collection->forceFill([
                'collection_code' => sprintf(
                    'COL-%06d',
                    $collection->id
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

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function collectionPoint(): BelongsTo
    {
        return $this->belongsTo(
            CollectionPoint::class
        );
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(
            CoffeePurchase::class,
            'agent_collection_id'
        );
    }

    public function fieldWeighings(): HasMany
    {
        return $this->hasMany(
            FieldWeighing::class,
            'agent_collection_id'
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
}
