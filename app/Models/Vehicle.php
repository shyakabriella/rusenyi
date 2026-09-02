<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_TRIP = 'in_trip';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_TRIP,
        self::STATUS_MAINTENANCE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'vehicle_code',
        'registration_number',
        'vehicle_type',
        'make',
        'model',
        'manufacture_year',
        'capacity_kg',
        'assigned_driver_id',
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
            'manufacture_year' => 'integer',
            'capacity_kg' => 'decimal:2',
            'assigned_driver_id' => 'integer',
            'status_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $vehicle) {
            if ($vehicle->vehicle_code) {
                return;
            }

            $vehicle->forceFill([
                'vehicle_code' => sprintf(
                    'VEH-%06d',
                    $vehicle->id
                ),
            ])->saveQuietly();
        });
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(
            Driver::class,
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
