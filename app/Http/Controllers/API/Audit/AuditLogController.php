<?php

namespace App\Http\Controllers\API\Audit;

use App\Http\Controllers\API\BaseController;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends BaseController
{
    public function index(
        Request $request
    ): JsonResponse {
        if (!$this->isAdmin($request)) {
            return $this->sendError(
                'Only Admin can view the Audit Trail.',
                [],
                403
            );
        }

        $query = AuditLog::query()
            ->with([
                'user:id,name,email,role',
            ]);

        if ($request->filled('search')) {
            $search =
                trim(
                    (string) $request->search
                );

            $query->where(
                function (
                    Builder $query
                ) use ($search) {
                    $query
                        ->where(
                            'audit_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'auditable_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'user_name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('module')) {
            $query->where(
                'module',
                $request->module
            );
        }

        if ($request->filled('action')) {
            $query->where(
                'action',
                $request->action
            );
        }

        if ($request->filled('user_id')) {
            $query->where(
                'user_id',
                $request->user_id
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->date_to
            );
        }

        $items = $query
            ->latest('id')
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
                    fn (AuditLog $audit) =>
                        $this->data(
                            $audit
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
        ], 'Audit Trail retrieved successfully.');
    }

    public function summary(
        Request $request
    ): JsonResponse {
        if (!$this->isAdmin($request)) {
            return $this->sendError(
                'Only Admin can view the Audit Trail.',
                [],
                403
            );
        }

        return $this->sendResponse([
            'total_logs' =>
                AuditLog::count(),

            'created_actions' =>
                AuditLog::where(
                    'action',
                    AuditLog::ACTION_CREATED
                )->count(),

            'updated_actions' =>
                AuditLog::where(
                    'action',
                    AuditLog::ACTION_UPDATED
                )->count(),

            'deleted_actions' =>
                AuditLog::where(
                    'action',
                    AuditLog::ACTION_DELETED
                )->count(),

            'today' =>
                AuditLog::whereDate(
                    'created_at',
                    today()
                )->count(),

            'active_users' =>
                AuditLog::query()
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id'),
        ], 'Audit summary retrieved successfully.');
    }

    public function show(
        Request $request,
        AuditLog $auditLog
    ): JsonResponse {
        if (!$this->isAdmin($request)) {
            return $this->sendError(
                'Only Admin can view the Audit Trail.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $auditLog->load(
                    'user:id,name,email,role'
                )
            ),
            'Audit record retrieved successfully.'
        );
    }

    private function isAdmin(
        Request $request
    ): bool {
        return $request->user()?->role ===
            'admin';
    }

    private function data(
        AuditLog $audit
    ): array {
        return [
            'id' =>
                $audit->id,

            'audit_code' =>
                $audit->audit_code,

            'action' =>
                $audit->action,

            'module' =>
                $audit->module,

            'description' =>
                $audit->description,

            'auditable_type' =>
                $audit->auditable_type,

            'auditable_id' =>
                $audit->auditable_id,

            'auditable_code' =>
                $audit->auditable_code,

            'old_values' =>
                $audit->old_values,

            'new_values' =>
                $audit->new_values,

            'request_method' =>
                $audit->request_method,

            'request_path' =>
                $audit->request_path,

            'route_name' =>
                $audit->route_name,

            'ip_address' =>
                $audit->ip_address,

            'user_agent' =>
                $audit->user_agent,

            'user_name' =>
                $audit->user_name,

            'user_role' =>
                $audit->user_role,

            'user' =>
                $audit->user,

            'created_at' =>
                $audit->created_at
                    ?->toISOString(),
        ];
    }
}
