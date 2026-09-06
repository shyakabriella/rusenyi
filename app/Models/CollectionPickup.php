<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionPickup extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PICKUP_CONFIRMED = 'pickup_confirmed';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_FACTORY_WEIGHED = 'factory_weighed';
    public const STATUS_FACTORY_ACKNOWLEDGED = 'factory_acknowledged';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_PICKUP_CONFIRMED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_ARRIVED,
        self::STATUS_FACTORY_WEIGHED,
        self::STATUS_FACTORY_ACKNOWLEDGED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'pickup_code',
        'receipt_code',
        'agent_collection_id',
        'agent_id',
        'driver_id',
        'vehicle_registration',
        'declared_quantity_kg',
        'pickup_weight_kg',
        'pickup_difference_kg',
        'factory_weight_kg',
        'factory_difference_kg',
        'status',
        'request_note',
        'response_note',
        'weight_note',
        'factory_note',
        'requested_by',
        'requested_at',
        'accepted_by',
        'accepted_at',
        'rejected_by',
        'rejected_at',
        'pickup_confirmed_by',
        'pickup_confirmed_at',
        'departed_at',
        'arrived_at',
        'factory_weighed_by',
        'factory_weighed_at',
        'factory_acknowledged_by',
        'factory_acknowledged_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'declared_quantity_kg' => 'decimal:2',
            'pickup_weight_kg' => 'decimal:2',
            'pickup_difference_kg' => 'decimal:2',
            'factory_weight_kg' => 'decimal:2',
            'factory_difference_kg' => 'decimal:2',

            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'pickup_confirmed_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'factory_weighed_at' => 'datetime',
            'factory_acknowledged_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(
            AgentCollection::class,
            'agent_collection_id'
        );
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'accepted_by'
        );
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'rejected_by'
        );
    }

    public function pickupConfirmer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'pickup_confirmed_by'
        );
    }

    public function factoryWeigher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'factory_weighed_by'
        );
    }

    public function factoryAcknowledger(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'factory_acknowledged_by'
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
