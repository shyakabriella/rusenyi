<?php

namespace App\Http\Controllers\API\Approval;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Approval\ApproveApprovalRequest;
use App\Http\Requests\API\Approval\CancelApprovalRequest;
use App\Http\Requests\API\Approval\RejectApprovalRequest;
use App\Http\Requests\API\Approval\RequestPayrollPaymentApprovalRequest;
use App\Models\ApprovalRequest;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\SystemNotificationService;

class ApprovalController extends BaseController
{
    public function __construct(
        private SystemNotificationService $notifications
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Approvals.',
                [],
                403
            );
        }

        $query = ApprovalRequest::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where(
                        'approval_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'reference_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'title',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
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

        if ($request->filled('requested_by')) {
            $query->where(
                'requested_by',
                $request->requested_by
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'requested_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'requested_at',
                '<=',
                $request->date_to
            );
        }

        $items = $query
            ->orderByRaw(
                "CASE WHEN status = 'pending' THEN 0 ELSE 1 END"
            )
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
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
                    fn (ApprovalRequest $approval) =>
                        $this->data($approval)
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
        ], 'Approval requests retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Approval summary.',
                [],
                403
            );
        }

        $pending = ApprovalRequest::query()
            ->where(
                'status',
                ApprovalRequest::STATUS_PENDING
            );

        return $this->sendResponse([
            'total_requests' =>
                ApprovalRequest::count(),

            'pending_requests' =>
                (clone $pending)->count(),

            'approved_requests' =>
                ApprovalRequest::where(
                    'status',
                    ApprovalRequest::STATUS_APPROVED
                )->count(),

            'rejected_requests' =>
                ApprovalRequest::where(
                    'status',
                    ApprovalRequest::STATUS_REJECTED
                )->count(),

            'cancelled_requests' =>
                ApprovalRequest::where(
                    'status',
                    ApprovalRequest::STATUS_CANCELLED
                )->count(),

            'pending_amount' =>
                $this->money(
                    (clone $pending)->sum('amount')
                ),

            'approved_unapplied_amount' =>
                $this->money(
                    ApprovalRequest::query()
                        ->where(
                            'status',
                            ApprovalRequest::STATUS_APPROVED
                        )
                        ->whereNull('applied_at')
                        ->sum('amount')
                ),

            'currency' => 'RWF',
        ], 'Approval summary retrieved successfully.');
    }

    public function show(
        Request $request,
        ApprovalRequest $approvalRequest
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Approval.',
                [],
                403
            );
        }


        return $this->sendResponse(
            $this->data(
                $approvalRequest->load(
                    $this->relations()
                )
            ),
            'Approval request retrieved successfully.'
        );
    }

    public function requestPayrollPayment(
        RequestPayrollPaymentApprovalRequest $request,
        Payroll $payroll
    ): JsonResponse {
        if (
            $payroll->status !==
            Payroll::STATUS_PROCESSED
        ) {
            return $this->sendError(
                'Only processed Payroll can be submitted for payment approval.',
                [],
                422
            );
        }

        $approval = DB::transaction(
            function () use (
                $request,
                $payroll
            ) {
                $lockedPayroll = Payroll::query()
                    ->lockForUpdate()
                    ->findOrFail($payroll->id);

                if (
                    $lockedPayroll->status !==
                    Payroll::STATUS_PROCESSED
                ) {
                    throw ValidationException::withMessages([
                        'payroll' => [
                            'Payroll is no longer in processed status.',
                        ],
                    ]);
                }

                $existing = ApprovalRequest::query()
                    ->where(
                        'module',
                        ApprovalRequest::MODULE_PAYROLL
                    )
                    ->where(
                        'action',
                        ApprovalRequest::ACTION_PAYMENT
                    )
                    ->where(
                        'reference_type',
                        'payroll'
                    )
                    ->where(
                        'reference_id',
                        $lockedPayroll->id
                    )
                    ->whereIn(
                        'status',
                        [
                            ApprovalRequest::STATUS_PENDING,
                            ApprovalRequest::STATUS_APPROVED,
                        ]
                    )
                    ->whereNull('applied_at')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'payroll' => [
                            'This Payroll already has an active payment approval request.',
                        ],
                    ]);
                }

                return ApprovalRequest::create([
                    'module' =>
                        ApprovalRequest::MODULE_PAYROLL,

                    'action' =>
                        ApprovalRequest::ACTION_PAYMENT,

                    'reference_type' =>
                        'payroll',

                    'reference_id' =>
                        $lockedPayroll->id,

                    'reference_code' =>
                        $lockedPayroll->payroll_code,

                    'title' =>
                        'Payroll payment approval for ' .
                        $lockedPayroll->employee_name,

                    'description' =>
                        'Approve payment of ' .
                        number_format(
                            (float) $lockedPayroll->net_salary,
                            0
                        ) .
                        ' RWF for ' .
                        $lockedPayroll->payroll_month .
                        '.',

                    'amount' =>
                        $lockedPayroll->net_salary,

                    'currency' =>
                        $lockedPayroll->currency,

                    'status' =>
                        ApprovalRequest::STATUS_PENDING,

                    'request_note' =>
                        $request->request_note,

                    'requested_by' =>
                        $request->user()->id,

                    'requested_at' =>
                        now(),
                ]);
            }
        );

        $this->notifications->sendToAdmins([
            'type' =>
                \App\Models\SystemNotification::TYPE_APPROVAL_REQUESTED,

            'title' =>
                'Approval request waiting for review',

            'message' =>
                $approval->reference_code .
                ' requires approval for ' .
                number_format(
                    (float) $approval->amount,
                    0
                ) .
                ' RWF.',

            'module' =>
                'approval',

            'reference_type' =>
                'approval_request',

            'reference_id' =>
                $approval->id,

            'reference_code' =>
                $approval->approval_code,

            'action_url' =>
                '/dashboard/finance/approvals',

            'data' => [
                'approval_id' =>
                    $approval->id,

                'payroll_id' =>
                    $payroll->id,
            ],

            'created_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $approval
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Payroll payment approval requested successfully.',
            201
        );
    }

    public function approve(
        ApproveApprovalRequest $request,
        ApprovalRequest $approvalRequest
    ): JsonResponse {
        if (
            $approvalRequest->status !==
            ApprovalRequest::STATUS_PENDING
        ) {
            return $this->sendError(
                'Only pending Approval requests can be approved.',
                [],
                422
            );
        }

        if (
            $approvalRequest->requested_by ===
            $request->user()->id
        ) {
            return $this->sendError(
                'You cannot approve your own Approval request.',
                [],
                422
            );
        }

        $this->validateReference(
            $approvalRequest
        );

        $approvalRequest->update([
            'status' =>
                ApprovalRequest::STATUS_APPROVED,

            'reviewed_by' =>
                $request->user()->id,

            'reviewed_at' =>
                now(),

            'review_note' =>
                $request->review_note,
        ]);

        $this->notifications->sendToUser(
            $approvalRequest->requested_by,
            [
                'type' =>
                    \App\Models\SystemNotification::TYPE_APPROVAL_APPROVED,

                'title' =>
                    'Your approval request was approved',

                'message' =>
                    $approvalRequest->approval_code .
                    ' for ' .
                    ($approvalRequest->reference_code ?? 'the request') .
                    ' was approved.',

                'module' =>
                    'approval',

                'reference_type' =>
                    'approval_request',

                'reference_id' =>
                    $approvalRequest->id,

                'reference_code' =>
                    $approvalRequest->approval_code,

                'action_url' =>
                    '/dashboard/finance/approvals',

                'data' => [
                    'approval_id' =>
                        $approvalRequest->id,
                ],

                'created_by' =>
                    $request->user()->id,
            ]
        );

        return $this->sendResponse(
            $this->data(
                $approvalRequest
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Approval request approved successfully.'
        );
    }

    public function reject(
        RejectApprovalRequest $request,
        ApprovalRequest $approvalRequest
    ): JsonResponse {
        if (
            $approvalRequest->status !==
            ApprovalRequest::STATUS_PENDING
        ) {
            return $this->sendError(
                'Only pending Approval requests can be rejected.',
                [],
                422
            );
        }

        if (
            $approvalRequest->requested_by ===
            $request->user()->id
        ) {
            return $this->sendError(
                'You cannot reject your own Approval request.',
                [],
                422
            );
        }

        $approvalRequest->update([
            'status' =>
                ApprovalRequest::STATUS_REJECTED,

            'reviewed_by' =>
                $request->user()->id,

            'reviewed_at' =>
                now(),

            'review_note' =>
                trim(
                    $request->review_note
                ),
        ]);

        $this->notifications->sendToUser(
            $approvalRequest->requested_by,
            [
                'type' =>
                    \App\Models\SystemNotification::TYPE_APPROVAL_REJECTED,

                'title' =>
                    'Your approval request was rejected',

                'message' =>
                    $approvalRequest->approval_code .
                    ' was rejected. Review the reason and correct the request if needed.',

                'module' =>
                    'approval',

                'reference_type' =>
                    'approval_request',

                'reference_id' =>
                    $approvalRequest->id,

                'reference_code' =>
                    $approvalRequest->approval_code,

                'action_url' =>
                    '/dashboard/finance/approvals',

                'data' => [
                    'approval_id' =>
                        $approvalRequest->id,
                ],

                'created_by' =>
                    $request->user()->id,
            ]
        );

        return $this->sendResponse(
            $this->data(
                $approvalRequest
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Approval request rejected successfully.'
        );
    }

    public function cancel(
        CancelApprovalRequest $request,
        ApprovalRequest $approvalRequest
    ): JsonResponse {
        if (
            $approvalRequest->status !==
            ApprovalRequest::STATUS_PENDING
        ) {
            return $this->sendError(
                'Only pending Approval requests can be cancelled.',
                [],
                422
            );
        }

        $user = $request->user();

        if (
            $user->role !== 'admin' &&
            $approvalRequest->requested_by !==
                $user->id
        ) {
            return $this->sendError(
                'You cannot cancel this Approval request.',
                [],
                403
            );
        }

        $approvalRequest->update([
            'status' =>
                ApprovalRequest::STATUS_CANCELLED,

            'cancelled_by' =>
                $user->id,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                trim(
                    $request->cancellation_reason
                ),
        ]);

        return $this->sendResponse(
            $this->data(
                $approvalRequest
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Approval request cancelled successfully.'
        );
    }

    private function validateReference(
        ApprovalRequest $approval
    ): void {
        if (
            $approval->module ===
                ApprovalRequest::MODULE_PAYROLL &&
            $approval->action ===
                ApprovalRequest::ACTION_PAYMENT
        ) {
            $payroll = Payroll::find(
                $approval->reference_id
            );

            if (
                !$payroll ||
                $payroll->status !==
                    Payroll::STATUS_PROCESSED
            ) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'The referenced Payroll is no longer eligible for payment approval.',
                    ],
                ]);
            }

            if (
                round(
                    (float) $approval->amount,
                    2
                ) !==
                round(
                    (float) $payroll->net_salary,
                    2
                )
            ) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'Payroll amount has changed since this Approval request was created.',
                    ],
                ]);
            }
        }
    }

    private function canRead(?User $user): bool
    {
        return $user &&
            in_array(
                $user->role,
                [
                    'admin',
                    'accountant',
                ],
                true
            );
    }

    private function relations(): array
    {
        return [
            'requester:id,name,email',
            'reviewer:id,name,email',
            'applier:id,name,email',
            'canceller:id,name,email',
        ];
    }

    private function data(
        ApprovalRequest $approval
    ): array {
        $payroll = null;

        if (
            $approval->module ===
                ApprovalRequest::MODULE_PAYROLL &&
            $approval->reference_type ===
                'payroll'
        ) {
            $payroll = Payroll::query()
                ->select([
                    'id',
                    'payroll_code',
                    'employee_id',
                    'employee_name',
                    'employee_role',
                    'payroll_month',
                    'gross_salary',
                    'net_salary',
                    'currency',
                    'status',
                    'payment_method',
                    'payment_reference',
                    'payment_date',
                ])
                ->find(
                    $approval->reference_id
                );
        }

        return [
            'id' =>
                $approval->id,

            'approval_code' =>
                $approval->approval_code,

            'module' =>
                $approval->module,

            'action' =>
                $approval->action,

            'reference_type' =>
                $approval->reference_type,

            'reference_id' =>
                $approval->reference_id,

            'reference_code' =>
                $approval->reference_code,

            'title' =>
                $approval->title,

            'description' =>
                $approval->description,

            'amount' =>
                $approval->amount,

            'currency' =>
                $approval->currency,

            'status' =>
                $approval->status,

            'request_note' =>
                $approval->request_note,

            'requested_at' =>
                $approval->requested_at
                    ?->toISOString(),

            'reviewed_at' =>
                $approval->reviewed_at
                    ?->toISOString(),

            'review_note' =>
                $approval->review_note,

            'applied_at' =>
                $approval->applied_at
                    ?->toISOString(),

            'cancelled_at' =>
                $approval->cancelled_at
                    ?->toISOString(),

            'cancellation_reason' =>
                $approval->cancellation_reason,

            'requester' =>
                $approval->requester,

            'reviewer' =>
                $approval->reviewer,

            'applier' =>
                $approval->applier,

            'canceller' =>
                $approval->canceller,

            'payroll' =>
                $payroll,
        ];
    }

    private function money($value): string
    {
        return number_format(
            (float) ($value ?? 0),
            2,
            '.',
            ''
        );
    }
}
