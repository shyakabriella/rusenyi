<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightReconciliation extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RECONCILED = 'reconciled';
    public const STATUS_CANCELLED = 'cancelled';

    public const OUTCOME_WITHIN_TOLERANCE = 'within_tolerance';
    public const OUTCOME_SHORTAGE = 'shortage';
    public const OUTCOME_EXCESS = 'excess';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RECONCILED,
        self::STATUS_CANCELLED,
    ];

    public const OUTCOMES = [
        self::OUTCOME_WITHIN_TOLERANCE,
        self::OUTCOME_SHORTAGE,
        self::OUTCOME_EXCESS,
    ];

    protected $fillable = [
        'reconciliation_code',
        'factory_reception_id',
        'coffee_season_id',
        'agent_collection_id',
        'field_weighing_id',
        'agent_id',
        'collection_point_id',
        'field_weight_kg',
        'factory_weight_kg',
        'difference_kg',
        'difference_percentage',
        'tolerance_percentage',
        'outcome',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'reconciled_by',
        'reconciled_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'factory_reception_id' => 'integer',
            'coffee_season_id' => 'integer',
            'agent_collection_id' => 'integer',
            'field_weighing_id' => 'integer',
            'agent_id' => 'integer',
            'collection_point_id' => 'integer',

            'field_weight_kg' => 'decimal:2',
            'factory_weight_kg' => 'decimal:2',
            'difference_kg' => 'decimal:2',
            'difference_percentage' => 'decimal:4',
            'tolerance_percentage' => 'decimal:2',

            'reconciled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $reconciliation) {
            if ($reconciliation->reconciliation_code) {
                return;
            }

            $reconciliation->forceFill([
                'reconciliation_code' => sprintf(
                    'WR-%06d',
                    $reconciliation->id
                ),
            ])->saveQuietly();
        });
    }

    public function factoryReception(): BelongsTo
    {
        return $this->belongsTo(
            FactoryReception::class
        );
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

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reconciled_by'
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
