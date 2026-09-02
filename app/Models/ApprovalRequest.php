<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    public const MODULE_PAYROLL = 'payroll';

    public const ACTION_PAYMENT = 'payment';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'approval_code',
        'module',
        'action',
        'reference_type',
        'reference_id',
        'reference_code',
        'title',
        'description',
        'amount',
        'currency',
        'status',
        'request_note',
        'requested_by',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'applied_by',
        'applied_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'reference_id' => 'integer',
            'amount' => 'decimal:2',

            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $approval) {
            if ($approval->approval_code) {
                return;
            }

            $approval->forceFill([
                'approval_code' => sprintf(
                    'APP-%06d',
                    $approval->id
                ),
            ])->saveQuietly();
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'applied_by'
        );
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(
            Payroll::class,
            'reference_id'
        );
    }
}
