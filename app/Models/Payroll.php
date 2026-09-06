<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_MOBILE_MONEY = 'mobile_money';
    public const PAYMENT_BANK_TRANSFER = 'bank_transfer';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PROCESSED,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_MOBILE_MONEY,
        self::PAYMENT_BANK_TRANSFER,
    ];

    protected $fillable = [
        'payroll_code',
        'employee_id',
        'worker_id',
        'employee_name',
        'employee_role',
        'payroll_month',
        'basic_salary',
        'allowances',
        'gross_salary',
        'deductions',
        'net_salary',
        'currency',
        'status',
        'payment_approval_id',
        'payment_method',
        'payment_reference',
        'payment_date',
        'notes',
        'created_by',
        'updated_by',
        'processed_by',
        'processed_at',
        'paid_by',
        'paid_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'worker_id' => 'integer',

            'basic_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',

            'payment_approval_id' => 'integer',
            'payment_date' => 'date',

            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $payroll) {
            if ($payroll->payroll_code) {
                return;
            }

            $payroll->forceFill([
                'payroll_code' => sprintf(
                    'PAY-%06d',
                    $payroll->id
                ),
            ])->saveQuietly();
        });
    }


    public function paymentApproval(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalRequest::class,
            'payment_approval_id'
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'employee_id'
        );
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'worker_id'
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

    public function processor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'processed_by'
        );
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'paid_by'
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
