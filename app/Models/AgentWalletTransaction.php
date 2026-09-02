<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentWalletTransaction extends Model
{
    public const DIRECTION_CREDIT = 'credit';
    public const DIRECTION_DEBIT = 'debit';

    public const TYPE_CASH_ALLOCATION = 'cash_allocation';
    public const TYPE_CASH_ALLOCATION_REVERSAL = 'cash_allocation_reversal';

    public const TYPE_COFFEE_PURCHASE = 'coffee_purchase';
    public const TYPE_COFFEE_PURCHASE_REVERSAL = 'coffee_purchase_reversal';

    protected $fillable = [
        'transaction_code',
        'agent_id',
        'coffee_season_id',
        'type',
        'direction',
        'amount',
        'currency',
        'source_type',
        'source_id',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'agent_id' => 'integer',
            'coffee_season_id' => 'integer',
            'source_id' => 'integer',
            'amount' => 'decimal:2',
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
                    'WLT-%06d',
                    $transaction->id
                ),
            ])->saveQuietly();
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function coffeeSeason(): BelongsTo
    {
        return $this->belongsTo(CoffeeSeason::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
