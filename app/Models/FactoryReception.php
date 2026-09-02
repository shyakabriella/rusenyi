<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactoryReception extends Model
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
        'reception_code',
        'coffee_season_id',
        'agent_collection_id',
        'field_weighing_id',
        'agent_id',
        'collection_point_id',
        'collection_trip_id',
        'balance_officer_id',
        'field_weight_kg',
        'factory_weight_kg',
        'difference_kg',
        'difference_percentage',
        'bag_count',
        'received_at',
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
            'field_weighing_id' => 'integer',
            'agent_id' => 'integer',
            'collection_point_id' => 'integer',
            'collection_trip_id' => 'integer',
            'balance_officer_id' => 'integer',

            'field_weight_kg' => 'decimal:2',
            'factory_weight_kg' => 'decimal:2',
            'difference_kg' => 'decimal:2',
            'difference_percentage' => 'decimal:4',

            'bag_count' => 'integer',
            'received_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $reception) {
            if ($reception->reception_code) {
                return;
            }

            $reception->forceFill([
                'reception_code' => sprintf(
                    'FR-%06d',
                    $reception->id
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
        return $this->belongsTo(Agent::class);
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

    public function weightReconciliations(): HasMany
    {
        return $this->hasMany(
            WeightReconciliation::class
        );
    }

}
