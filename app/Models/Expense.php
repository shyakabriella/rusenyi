<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RECORDED = 'recorded';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_MOBILE_MONEY = 'mobile_money';
    public const PAYMENT_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_OTHER = 'other';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RECORDED,
        self::STATUS_CANCELLED,
    ];

    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_MOBILE_MONEY,
        self::PAYMENT_BANK_TRANSFER,
        self::PAYMENT_OTHER,
    ];

    protected $fillable = [
        'expense_code',
        'expense_date',
        'category',
        'payee_name',
        'description',
        'amount',
        'currency',
        'payment_method',
        'payment_reference',
        'receipt_number',
        'source_type',
        'source_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'recorded_by',
        'recorded_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',

            'source_id' => 'integer',

            'recorded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $expense) {
            if ($expense->expense_code) {
                return;
            }

            $expense->forceFill([
                'expense_code' => sprintf(
                    'EXP-%06d',
                    $expense->id
                ),
            ])->saveQuietly();
        });
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
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
