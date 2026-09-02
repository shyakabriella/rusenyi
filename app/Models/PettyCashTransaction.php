<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashTransaction extends Model
{
    public const TYPE_FUND_IN = 'fund_in';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_REVERSAL = 'reversal';

    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    public const POSTABLE_TYPES = [
        self::TYPE_FUND_IN,
        self::TYPE_EXPENSE,
    ];

    protected $fillable = [
        'transaction_code',
        'transaction_date',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'currency',
        'category',
        'counterparty_name',
        'purpose',
        'reference_number',
        'receipt_number',
        'expense_id',
        'reverses_transaction_id',
        'status',
        'notes',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',

            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',

            'expense_id' => 'integer',
            'reverses_transaction_id' => 'integer',

            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $transaction) {
            if ($transaction->transaction_code) {
                return;
            }

            $transaction->forceFill([
                'transaction_code' => sprintf(
                    'PC-%06d',
                    $transaction->id
                ),
            ])->saveQuietly();
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(
            Expense::class
        );
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'posted_by'
        );
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reversed_by'
        );
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'reverses_transaction_id'
        );
    }
}
