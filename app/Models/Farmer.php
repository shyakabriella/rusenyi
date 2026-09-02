<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Farmer extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public const GENDER_MALE = 'male';
    public const GENDER_FEMALE = 'female';
    public const GENDER_OTHER = 'other';

    public const GENDERS = [
        self::GENDER_MALE,
        self::GENDER_FEMALE,
        self::GENDER_OTHER,
    ];

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_MOBILE_MONEY = 'mobile_money';
    public const PAYMENT_BANK = 'bank';

    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_MOBILE_MONEY,
        self::PAYMENT_BANK,
    ];

    protected $fillable = [
        'farmer_code',
        'full_name',
        'phone',
        'national_id',
        'gender',
        'village_id',
        'collection_point_id',
        'preferred_payment_method',
        'address_note',
        'notes',
        'status',
        'created_by',
        'updated_by',
        'deactivated_by',
        'deactivated_at',
        'reactivated_by',
        'reactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'village_id' => 'integer',
            'collection_point_id' => 'integer',

            'deactivated_at' =>
                'datetime',

            'reactivated_at' =>
                'datetime',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(
            Village::class
        );
    }

    public function collectionPoint(): BelongsTo
    {
        return $this->belongsTo(
            CollectionPoint::class
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

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'deactivated_by'
        );
    }

    public function reactivator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reactivated_by'
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_ACTIVE
        );
    }

    public function scopeInactive(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_INACTIVE
        );
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status ===
            self::STATUS_ACTIVE;
    }
}
