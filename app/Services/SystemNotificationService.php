<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class SystemNotificationService
{
    public function sendToUser(
        int|User $recipient,
        array $data
    ): SystemNotification {
        $recipientId = $recipient instanceof User
            ? $recipient->id
            : $recipient;

        return SystemNotification::create([
            'recipient_id' =>
                $recipientId,

            'type' =>
                $data['type'],

            'title' =>
                $data['title'],

            'message' =>
                $data['message'],

            'module' =>
                $data['module'] ?? null,

            'reference_type' =>
                $data['reference_type'] ?? null,

            'reference_id' =>
                $data['reference_id'] ?? null,

            'reference_code' =>
                $data['reference_code'] ?? null,

            'action_url' =>
                $data['action_url'] ?? null,

            'data' =>
                $data['data'] ?? null,

            'created_by' =>
                $data['created_by'] ?? null,
        ]);
    }

    public function sendToAdmins(
        array $data
    ): Collection {
        $admins = User::query()
            ->where(
                'role',
                'admin'
            )
            ->where(
                'is_active',
                true
            )
            ->get();

        return $admins->map(
            function (User $admin) use ($data) {
                return $this->sendToUser(
                    $admin->id,
                    $data
                );
            }
        );
    }
}
