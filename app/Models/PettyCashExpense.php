<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashExpense extends Model
{
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'expense_code',
        'accountant_id',
        'expense_date',
        'category',
        'payee',
        'amount',
        'currency',
        'description',
        'receipt_path',
        'receipt_name',
        'status',
        'created_by',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $expense) {
            if ($expense->expense_code) {
                return;
            }

            $expense->forceFill([
                'expense_code' =>
                    sprintf(
                        'PCE-%06d',
                        $expense->id
                    ),
            ])->saveQuietly();
        });
    }

    public function accountant(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'accountant_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reversed_by'
        );
    }
}
