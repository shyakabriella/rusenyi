<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemNotification extends Model
{
    public const TYPE_APPROVAL_REQUESTED =
        'approval_requested';

    public const TYPE_APPROVAL_APPROVED =
        'approval_approved';

    public const TYPE_APPROVAL_REJECTED =
        'approval_rejected';

    public const TYPE_APPROVAL_CANCELLED =
        'approval_cancelled';

    protected $fillable = [
        'notification_code',
        'recipient_id',
        'type',
        'title',
        'message',
        'module',
        'reference_type',
        'reference_id',
        'reference_code',
        'action_url',
        'data',
        'read_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'recipient_id' => 'integer',
            'reference_id' => 'integer',
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $notification) {
            if ($notification->notification_code) {
                return;
            }

            $notification->forceFill([
                'notification_code' => sprintf(
                    'NOT-%06d',
                    $notification->id
                ),
            ])->saveQuietly();
        });
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recipient_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
