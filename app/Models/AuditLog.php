<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';

    protected $fillable = [
        'audit_code',
        'user_id',
        'user_name',
        'user_role',
        'action',
        'module',
        'auditable_type',
        'auditable_id',
        'auditable_code',
        'description',
        'old_values',
        'new_values',
        'request_method',
        'request_path',
        'route_name',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'auditable_id' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $audit) {
            if ($audit->audit_code) {
                return;
            }

            $audit->forceFill([
                'audit_code' => sprintf(
                    'AUD-%06d',
                    $audit->id
                ),
            ])->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }
}
