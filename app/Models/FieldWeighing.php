<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldWeighing extends Model
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
        'weighing_code',
        'coffee_season_id',
        'agent_collection_id',
        'agent_id',
        'collection_point_id',
        'balance_officer_id',
        'expected_quantity_kg',
        'field_weight_kg',
        'difference_kg',
        'difference_percentage',
        'bag_count',
        'weighed_at',
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
            'coffee_season_id' => 'integer',
            'agent_collection_id' => 'integer',
            'agent_id' => 'integer',
            'collection_point_id' => 'integer',
            'balance_officer_id' => 'integer',

            'expected_quantity_kg' =>
                'decimal:2',

            'field_weight_kg' =>
                'decimal:2',

            'difference_kg' =>
                'decimal:2',

            'difference_percentage' =>
                'decimal:4',

            'bag_count' => 'integer',

            'weighed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $weighing) {
            if ($weighing->weighing_code) {
                return;
            }

            $weighing->forceFill([
                'weighing_code' => sprintf(
                    'FW-%06d',
                    $weighing->id
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

    public function balanceOfficer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'balance_officer_id'
        );
    }

    public function collectionTrips(): HasMany
    {
        return $this->hasMany(
            CollectionTrip::class,
            'field_weighing_id'
        );
    }


    public function factoryReceptions(): HasMany
    {
        return $this->hasMany(
            FactoryReception::class,
            'field_weighing_id'
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
