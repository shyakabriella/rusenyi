<?php

namespace App\Http\Controllers\API\Payroll;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Payroll\CancelPayrollRequest;
use App\Http\Requests\API\Payroll\PayPayrollRequest;
use App\Http\Requests\API\Payroll\StorePayrollRequest;
use App\Http\Requests\API\Payroll\UpdatePayrollRequest;
use App\Models\ApprovalRequest;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Payroll.',
                [],
                403
            );
        }

        $query = Payroll::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(
                function (Builder $query) use ($search) {
                    $query
                        ->where(
                            'payroll_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'employee_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'employee_role',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'payment_reference',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('payroll_month')) {
            $query->where(
                'payroll_month',
                $request->payroll_month
            );
        }

        if ($request->filled('employee_id')) {
            $query->where(
                'employee_id',
                $request->employee_id
            );
        }

        $items = $query
            ->orderByDesc('payroll_month')
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
                    fn (Payroll $payroll) =>
                        $this->data($payroll)
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
        ], 'Payroll records retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Payroll summary.',
                [],
                403
            );
        }

        $month = $request->get(
            'payroll_month',
            now()->format('Y-m')
        );

        $monthQuery = Payroll::query()
            ->where(
                'payroll_month',
                $month
            )
            ->where(
                'status',
                '!=',
                Payroll::STATUS_CANCELLED
            );

        $paidQuery = Payroll::query()
            ->where(
                'payroll_month',
                $month
            )
            ->where(
                'status',
                Payroll::STATUS_PAID
            );

        return $this->sendResponse([
            'payroll_month' =>
                $month,

            'total_records' =>
                (clone $monthQuery)->count(),

            'draft_records' =>
                Payroll::where(
                    'payroll_month',
                    $month
                )
                    ->where(
                        'status',
                        Payroll::STATUS_DRAFT
                    )
                    ->count(),

            'processed_records' =>
                Payroll::where(
                    'payroll_month',
                    $month
                )
                    ->where(
                        'status',
                        Payroll::STATUS_PROCESSED
                    )
                    ->count(),

            'paid_records' =>
                (clone $paidQuery)->count(),

            'gross_payroll' =>
                $this->money(
                    (clone $monthQuery)
                        ->sum('gross_salary')
                ),

            'total_deductions' =>
                $this->money(
                    (clone $monthQuery)
                        ->sum('deductions')
                ),

            'net_payroll' =>
                $this->money(
                    (clone $monthQuery)
                        ->sum('net_salary')
                ),

            'total_paid' =>
                $this->money(
                    (clone $paidQuery)
                        ->sum('net_salary')
                ),

            'currency' => 'RWF',
        ], 'Payroll summary retrieved successfully.');
    }

    public function employeeLookup(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Payroll employees.',
                [],
                403
            );
        }

        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
            ]);

        return $this->sendResponse([
            'items' => $users,
        ], 'Payroll employees retrieved successfully.');
    }

    public function store(
        StorePayrollRequest $request
    ): JsonResponse {
        $employee = User::findOrFail(
            $request->employee_id
        );

        $this->ensureUniqueActivePayroll(
            $employee->id,
            $request->payroll_month
        );

        $salary = $this->calculateSalary(
            $request->basic_salary,
            $request->allowances ?? 0,
            $request->deductions ?? 0
        );

        $payroll = Payroll::create([
            'employee_id' =>
                $employee->id,

            'employee_name' =>
                $employee->name,

            'employee_role' =>
                $employee->role,

            'payroll_month' =>
                $request->payroll_month,

            'basic_salary' =>
                $salary['basic_salary'],

            'allowances' =>
                $salary['allowances'],

            'gross_salary' =>
                $salary['gross_salary'],

            'deductions' =>
                $salary['deductions'],

            'net_salary' =>
                $salary['net_salary'],

            'currency' =>
                'RWF',

            'status' =>
                Payroll::STATUS_DRAFT,

            'notes' =>
                $request->notes,

            'created_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $payroll
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Payroll draft created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        Payroll $payroll
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Payroll record.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $payroll->load(
                    $this->relations()
                )
            ),
            'Payroll record retrieved successfully.'
        );
    }

    public function update(
        UpdatePayrollRequest $request,
        Payroll $payroll
    ): JsonResponse {
        if (
            $payroll->status !==
            Payroll::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Payroll records can be updated.',
                [],
                422
            );
        }

        $employeeId =
            $request->has('employee_id')
                ? (int) $request->employee_id
                : $payroll->employee_id;

        $month =
            $request->has('payroll_month')
                ? $request->payroll_month
                : $payroll->payroll_month;

        $this->ensureUniqueActivePayroll(
            $employeeId,
            $month,
            $payroll->id
        );

        $employee = User::findOrFail(
            $employeeId
        );

        $salary = $this->calculateSalary(
            $request->has('basic_salary')
                ? $request->basic_salary
                : $payroll->basic_salary,

            $request->has('allowances')
                ? ($request->allowances ?? 0)
                : $payroll->allowances,

            $request->has('deductions')
                ? ($request->deductions ?? 0)
                : $payroll->deductions
        );

        $payroll->update([
            'employee_id' =>
                $employee->id,

            'employee_name' =>
                $employee->name,

            'employee_role' =>
                $employee->role,

            'payroll_month' =>
                $month,

            'basic_salary' =>
                $salary['basic_salary'],

            'allowances' =>
                $salary['allowances'],

            'gross_salary' =>
                $salary['gross_salary'],

            'deductions' =>
                $salary['deductions'],

            'net_salary' =>
                $salary['net_salary'],

            'notes' =>
                $request->has('notes')
                    ? $request->notes
                    : $payroll->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $payroll
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Payroll updated successfully.'
        );
    }

    public function process(
        Request $request,
        Payroll $payroll
    ): JsonResponse {
        if (!$this->canManage($request->user())) {
            return $this->sendError(
                'You are not allowed to process Payroll.',
                [],
                403
            );
        }

        if (
            $payroll->status !==
            Payroll::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Payroll records can be processed.',
                [],
                422
            );
        }

        $payroll->update([
            'status' =>
                Payroll::STATUS_PROCESSED,

            'processed_by' =>
                $request->user()->id,

            'processed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $payroll
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Payroll processed successfully.'
        );
    }

    public function pay(
        PayPayrollRequest $request,
        Payroll $payroll
    ): JsonResponse {
        $this->validatePaymentReference(
            $request->payment_method,
            $request->payment_reference
        );

        $result = DB::transaction(
            function () use (
                $request,
                $payroll
            ) {
                $lockedPayroll = Payroll::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $payroll->id
                    );

                if (
                    $lockedPayroll->status !==
                    Payroll::STATUS_PROCESSED
                ) {
                    throw ValidationException::withMessages([
                        'payroll' => [
                            'Only processed Payroll records can be paid.',
                        ],
                    ]);
                }

                $approval = ApprovalRequest::query()
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
                    ->where(
                        'status',
                        ApprovalRequest::STATUS_APPROVED
                    )
                    ->whereNull('applied_at')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if (!$approval) {
                    throw ValidationException::withMessages([
                        'approval' => [
                            'Payroll payment requires an approved Approval request.',
                        ],
                    ]);
                }

                if (
                    round(
                        (float) $approval->amount,
                        2
                    ) !==
                    round(
                        (float) $lockedPayroll->net_salary,
                        2
                    )
                ) {
                    throw ValidationException::withMessages([
                        'approval' => [
                            'Approved amount does not match the current Net Salary.',
                        ],
                    ]);
                }

                $lockedPayroll->update([
                    'status' =>
                        Payroll::STATUS_PAID,

                    'payment_approval_id' =>
                        $approval->id,

                    'payment_method' =>
                        $request->payment_method,

                    'payment_reference' =>
                        $request->payment_reference
                            ? trim(
                                $request->payment_reference
                            )
                            : null,

                    'payment_date' =>
                        $request->payment_date,

                    'paid_by' =>
                        $request->user()->id,

                    'paid_at' =>
                        now(),

                    'updated_by' =>
                        $request->user()->id,
                ]);

                $approval->update([
                    'applied_by' =>
                        $request->user()->id,

                    'applied_at' =>
                        now(),
                ]);

                return $lockedPayroll;
            }
        );

        return $this->sendResponse(
            $this->data(
                $result
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Payroll payment recorded successfully.'
        );
    }

    public function cancel(
        CancelPayrollRequest $request,
        Payroll $payroll
    ): JsonResponse {
        if (
            $payroll->status ===
            Payroll::STATUS_PAID
        ) {
            return $this->sendError(
                'Paid Payroll cannot be cancelled directly.',
                [],
                422
            );
        }

        if (
            $payroll->status ===
            Payroll::STATUS_CANCELLED
        ) {
            return $this->sendError(
                'This Payroll record is already cancelled.',
                [],
                422
            );
        }

        $payroll->update([
            'status' =>
                Payroll::STATUS_CANCELLED,

            'cancelled_by' =>
                $request->user()->id,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                trim(
                    $request->cancellation_reason
                ),

            'updated_by' =>
                $request->user()->id,
        ]);

        // Cancel any unused approval linked to this Payroll.
        ApprovalRequest::query()
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
                $payroll->id
            )
            ->whereIn(
                'status',
                [
                    ApprovalRequest::STATUS_PENDING,
                    ApprovalRequest::STATUS_APPROVED,
                ]
            )
            ->whereNull('applied_at')
            ->update([
                'status' =>
                    ApprovalRequest::STATUS_CANCELLED,

                'cancelled_by' =>
                    $request->user()->id,

                'cancelled_at' =>
                    now(),

                'cancellation_reason' =>
                    'Payroll cancelled: ' .
                    trim(
                        $request->cancellation_reason
                    ),
            ]);

        return $this->sendResponse(
            $this->data(
                $payroll
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Payroll cancelled successfully.'
        );
    }

    private function ensureUniqueActivePayroll(
        int $employeeId,
        string $month,
        ?int $ignoreId = null
    ): void {
        $query = Payroll::query()
            ->where(
                'employee_id',
                $employeeId
            )
            ->where(
                'payroll_month',
                $month
            )
            ->where(
                'status',
                '!=',
                Payroll::STATUS_CANCELLED
            );

        if ($ignoreId) {
            $query->where(
                'id',
                '!=',
                $ignoreId
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'employee_id' => [
                    'This employee already has an active Payroll record for the selected month.',
                ],
            ]);
        }
    }

    private function calculateSalary(
        $basic,
        $allowances,
        $deductions
    ): array {
        $basic = round(
            (float) $basic,
            2
        );

        $allowances = round(
            (float) ($allowances ?? 0),
            2
        );

        $deductions = round(
            (float) ($deductions ?? 0),
            2
        );

        $gross = round(
            $basic + $allowances,
            2
        );

        if ($deductions > $gross) {
            throw ValidationException::withMessages([
                'deductions' => [
                    'Deductions cannot exceed the gross salary.',
                ],
            ]);
        }

        return [
            'basic_salary' =>
                $basic,

            'allowances' =>
                $allowances,

            'gross_salary' =>
                $gross,

            'deductions' =>
                $deductions,

            'net_salary' =>
                round(
                    $gross - $deductions,
                    2
                ),
        ];
    }

    private function validatePaymentReference(
        string $method,
        ?string $reference
    ): void {
        if (
            in_array(
                $method,
                [
                    Payroll::PAYMENT_MOBILE_MONEY,
                    Payroll::PAYMENT_BANK_TRANSFER,
                ],
                true
            ) &&
            blank($reference)
        ) {
            throw ValidationException::withMessages([
                'payment_reference' => [
                    'Payment reference is required for Mobile Money and Bank Transfer.',
                ],
            ]);
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

    private function canManage(?User $user): bool
    {
        return $this->canRead($user);
    }

    private function relations(): array
    {
        return [
            'employee:id,name,email,role',
            'paymentApproval:id,approval_code,status,reviewed_by,reviewed_at,applied_at',
            'creator:id,name',
            'updater:id,name',
            'processor:id,name',
            'payer:id,name',
            'canceller:id,name',
        ];
    }

    private function data(Payroll $payroll): array
    {
        return [
            'id' =>
                $payroll->id,

            'payroll_code' =>
                $payroll->payroll_code,

            'employee_id' =>
                $payroll->employee_id,

            'employee_name' =>
                $payroll->employee_name,

            'employee_role' =>
                $payroll->employee_role,

            'payroll_month' =>
                $payroll->payroll_month,

            'basic_salary' =>
                $payroll->basic_salary,

            'allowances' =>
                $payroll->allowances,

            'gross_salary' =>
                $payroll->gross_salary,

            'deductions' =>
                $payroll->deductions,

            'net_salary' =>
                $payroll->net_salary,

            'currency' =>
                $payroll->currency,

            'status' =>
                $payroll->status,

            'payment_approval_id' =>
                $payroll->payment_approval_id,

            'payment_approval' =>
                $payroll->paymentApproval,

            'payment_method' =>
                $payroll->payment_method,

            'payment_reference' =>
                $payroll->payment_reference,

            'payment_date' =>
                $payroll->payment_date
                    ?->format('Y-m-d'),

            'notes' =>
                $payroll->notes,

            'processed_at' =>
                $payroll->processed_at
                    ?->toISOString(),

            'paid_at' =>
                $payroll->paid_at
                    ?->toISOString(),

            'cancelled_at' =>
                $payroll->cancelled_at
                    ?->toISOString(),

            'cancellation_reason' =>
                $payroll->cancellation_reason,

            'employee' =>
                $payroll->employee,

            'creator' =>
                $payroll->creator,

            'updater' =>
                $payroll->updater,

            'processor' =>
                $payroll->processor,

            'payer' =>
                $payroll->payer,

            'canceller' =>
                $payroll->canceller,
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
