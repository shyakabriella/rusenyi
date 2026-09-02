<?php

namespace App\Http\Controllers\API\Notification;

use App\Http\Controllers\API\BaseController;
use App\Models\SystemNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = SystemNotification::query()
            ->where(
                'recipient_id',
                $request->user()->id
            )
            ->with([
                'creator:id,name',
            ]);

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(
                function (Builder $query) use ($search) {
                    $query
                        ->where(
                            'notification_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'message',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'reference_code',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'type',
                $request->type
            );
        }

        if ($request->filled('module')) {
            $query->where(
                'module',
                $request->module
            );
        }

        if ($request->filled('read_status')) {
            if (
                $request->read_status ===
                'unread'
            ) {
                $query->whereNull(
                    'read_at'
                );
            }

            if (
                $request->read_status ===
                'read'
            ) {
                $query->whereNotNull(
                    'read_at'
                );
            }
        }

        $items = $query
            ->orderByRaw(
                'CASE WHEN read_at IS NULL THEN 0 ELSE 1 END'
            )
            ->orderByDesc('created_at')
            ->paginate(
                min(
                    max(
                        (int) $request->get(
                            'per_page',
                            20
                        ),
                        1
                    ),
                    100
                )
            );

        return $this->sendResponse([
            'items' => collect(
                $items->items()
            )
                ->map(
                    fn (SystemNotification $notification) =>
                        $this->data(
                            $notification
                        )
                )
                ->values(),

            'pagination' => [
                'current_page' =>
                    $items->currentPage(),

                'last_page' =>
                    $items->lastPage(),

                'per_page' =>
                    $items->perPage(),

                'total' =>
                    $items->total(),
            ],
        ], 'Notifications retrieved successfully.');
    }

    public function summary(
        Request $request
    ): JsonResponse {
        $query = SystemNotification::query()
            ->where(
                'recipient_id',
                $request->user()->id
            );

        return $this->sendResponse([
            'total' =>
                (clone $query)->count(),

            'unread' =>
                (clone $query)
                    ->whereNull('read_at')
                    ->count(),

            'read' =>
                (clone $query)
                    ->whereNotNull('read_at')
                    ->count(),

            'today' =>
                (clone $query)
                    ->whereDate(
                        'created_at',
                        today()
                    )
                    ->count(),

            'approval_notifications' =>
                (clone $query)
                    ->where(
                        'module',
                        'approval'
                    )
                    ->count(),
        ], 'Notification summary retrieved successfully.');
    }

    public function show(
        Request $request,
        SystemNotification $systemNotification
    ): JsonResponse {
        if (
            $systemNotification->recipient_id !==
            $request->user()->id
        ) {
            return $this->sendError(
                'You are not allowed to view this notification.',
                [],
                403
            );
        }

        if (!$systemNotification->read_at) {
            $systemNotification->update([
                'read_at' => now(),
            ]);
        }

        return $this->sendResponse(
            $this->data(
                $systemNotification
                    ->fresh()
                    ->load(
                        'creator:id,name'
                    )
            ),
            'Notification retrieved successfully.'
        );
    }

    public function markRead(
        Request $request,
        SystemNotification $systemNotification
    ): JsonResponse {
        if (
            $systemNotification->recipient_id !==
            $request->user()->id
        ) {
            return $this->sendError(
                'You are not allowed to update this notification.',
                [],
                403
            );
        }

        if (!$systemNotification->read_at) {
            $systemNotification->update([
                'read_at' => now(),
            ]);
        }

        return $this->sendResponse(
            $this->data(
                $systemNotification
                    ->fresh()
                    ->load(
                        'creator:id,name'
                    )
            ),
            'Notification marked as read.'
        );
    }

    public function markUnread(
        Request $request,
        SystemNotification $systemNotification
    ): JsonResponse {
        if (
            $systemNotification->recipient_id !==
            $request->user()->id
        ) {
            return $this->sendError(
                'You are not allowed to update this notification.',
                [],
                403
            );
        }

        $systemNotification->update([
            'read_at' => null,
        ]);

        return $this->sendResponse(
            $this->data(
                $systemNotification
                    ->fresh()
                    ->load(
                        'creator:id,name'
                    )
            ),
            'Notification marked as unread.'
        );
    }

    public function markAllRead(
        Request $request
    ): JsonResponse {
        $count = SystemNotification::query()
            ->where(
                'recipient_id',
                $request->user()->id
            )
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return $this->sendResponse([
            'updated' => $count,
        ], 'All notifications marked as read.');
    }

    private function data(
        SystemNotification $notification
    ): array {
        return [
            'id' =>
                $notification->id,

            'notification_code' =>
                $notification
                    ->notification_code,

            'type' =>
                $notification->type,

            'title' =>
                $notification->title,

            'message' =>
                $notification->message,

            'module' =>
                $notification->module,

            'reference_type' =>
                $notification
                    ->reference_type,

            'reference_id' =>
                $notification
                    ->reference_id,

            'reference_code' =>
                $notification
                    ->reference_code,

            'action_url' =>
                $notification
                    ->action_url,

            'data' =>
                $notification->data,

            'is_read' =>
                $notification->read_at !== null,

            'read_at' =>
                $notification->read_at
                    ?->toISOString(),

            'created_at' =>
                $notification->created_at
                    ?->toISOString(),

            'creator' =>
                $notification->creator,
        ];
    }
}
