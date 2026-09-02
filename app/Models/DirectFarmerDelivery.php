<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectFarmerDelivery extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_MOBILE_MONEY = 'mobile_money';
    public const PAYMENT_BANK = 'bank';

    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_MOBILE_MONEY,
        self::PAYMENT_BANK,
    ];

    protected $fillable = [
        'delivery_code',
        'coffee_season_id',
        'coffee_price_id',
        'farmer_id',
        'balance_officer_id',
        'coffee_type',
        'quantity_kg',
        'price_per_kg',
        'total_amount',
        'currency',
        'delivery_date',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'payment_proof_path',
        'payment_proof_original_name',
        'payment_proof_mime_type',
        'payment_proof_size',
        'payment_proof_uploaded_by',
        'payment_proof_uploaded_at',
        'purpose',
        'created_by',
        'updated_by',
        'confirmed_by',
        'confirmed_at',
        'paid_by',
        'paid_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:2',
            'price_per_kg' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'delivery_date' => 'date',
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'payment_proof_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $delivery) {
            if ($delivery->delivery_code) {
                return;
            }

            $delivery->forceFill([
                'delivery_code' => sprintf(
                    'DFD-%06d',
                    $delivery->id
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

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
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
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function proofUploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'payment_proof_uploaded_by'
        );
    }
}
