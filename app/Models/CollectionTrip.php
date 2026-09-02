<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionTrip extends Model
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_ARRIVED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'trip_code',
        'coffee_season_id',
        'agent_collection_id',
        'field_weighing_id',
        'agent_id',
        'collection_point_id',
        'driver_user_id',
        'vehicle_registration',
        'field_weight_kg',
        'status',
        'departure_at',
        'arrived_at',
        'notes',
        'created_by',
        'updated_by',
        'started_by',
        'arrived_by',
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
            'agent_collection_id' => 'integer',
            'field_weighing_id' => 'integer',
            'agent_id' => 'integer',
            'collection_point_id' => 'integer',
            'driver_user_id' => 'integer',

            'field_weight_kg' => 'decimal:2',

            'departure_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $trip) {
            if ($trip->trip_code) {
                return;
            }

            $trip->forceFill([
                'trip_code' => sprintf(
                    'TRIP-%06d',
                    $trip->id
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

    public function agentCollection(): BelongsTo
    {
        return $this->belongsTo(
            AgentCollection::class
        );
    }

    public function fieldWeighing(): BelongsTo
    {
        return $this->belongsTo(
            FieldWeighing::class
        );
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(
            Agent::class
        );
    }

    public function collectionPoint(): BelongsTo
    {
        return $this->belongsTo(
            CollectionPoint::class
        );
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'driver_user_id'
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

    public function arriver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'arrived_by'
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
