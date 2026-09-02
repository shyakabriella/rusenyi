<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoffeePurchase extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'purchase_code',
        'coffee_season_id',
        'coffee_price_id',
        'agent_id',
        'farmer_id',
        'collection_point_id',
        'agent_collection_id',
        'coffee_type',
        'quantity_kg',
        'price_per_kg',
        'total_amount',
        'currency',
        'purchase_date',
        'status',
        'purpose',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'coffee_season_id' => 'integer',
            'coffee_price_id' => 'integer',
            'agent_id' => 'integer',
            'farmer_id' => 'integer',
            'collection_point_id' => 'integer',
            'agent_collection_id' => 'integer',

            'quantity_kg' => 'decimal:2',
            'price_per_kg' => 'decimal:2',
            'total_amount' => 'decimal:2',

            'purchase_date' => 'date',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $purchase) {
            if ($purchase->purchase_code) {
                return;
            }

            $purchase->forceFill([
                'purchase_code' => sprintf(
                    'PUR-%06d',
                    $purchase->id
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

    public function coffeePrice(): BelongsTo
    {
        return $this->belongsTo(CoffeePrice::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function collectionPoint(): BelongsTo
    {
        return $this->belongsTo(CollectionPoint::class);
    }

    public function agentCollection(): BelongsTo
    {
        return $this->belongsTo(
            AgentCollection::class,
            'agent_collection_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
