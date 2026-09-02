<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAllocation extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_CANCELLED,
    ];

    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_MOBILE_MONEY = 'mobile_money';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_MOBILE_MONEY,
        self::PAYMENT_METHOD_BANK_TRANSFER,
    ];

    protected $fillable = [
        'allocation_code',
        'coffee_season_id',
        'agent_id',
        'amount',
        'currency',

        'payment_method',

        /*
         * Existing reference column now represents
         * the external payment reference:
         * MoMo transaction, bank reference, voucher, etc.
         */
        'reference',

        'allocation_date',
        'purpose',
        'notes',

        'payment_proof_path',
        'payment_proof_original_name',
        'payment_proof_mime_type',
        'payment_proof_size',
        'payment_proof_uploaded_by',
        'payment_proof_uploaded_at',

        'status',

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
            'agent_id' => 'integer',

            'amount' => 'decimal:2',

            'allocation_date' => 'date',

            'payment_proof_size' => 'integer',

            'payment_proof_uploaded_at' => 'datetime',

            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function coffeeSeason(): BelongsTo
    {
        return $this->belongsTo(
            CoffeeSeason::class
        );
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(
            Agent::class
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function paymentProofUploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'payment_proof_uploaded_by'
        );
    }

    public function scopeDraft(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_DRAFT
        );
    }

    public function scopeApproved(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_APPROVED
        );
    }

    public function scopeCancelled(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_CANCELLED
        );
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status ===
            self::STATUS_DRAFT;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status ===
            self::STATUS_APPROVED;
    }
}
