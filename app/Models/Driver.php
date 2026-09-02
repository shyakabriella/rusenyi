<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_SUSPENDED,
    ];

    protected $fillable = [
        'driver_code',
        'user_id',
        'license_number',
        'license_category',
        'license_expiry_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'status_changed_by',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'license_expiry_date' => 'date',
            'status_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $driver) {
            if ($driver->driver_code) {
                return;
            }

            $driver->forceFill([
                'driver_code' => sprintf(
                    'DRV-%06d',
                    $driver->id
                ),
            ])->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedVehicle(): HasOne
    {
        return $this->hasOne(
            Vehicle::class,
            'assigned_driver_id'
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

    public function statusChanger(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'status_changed_by'
        );
    }
}
