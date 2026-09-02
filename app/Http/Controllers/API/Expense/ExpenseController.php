<?php

namespace App\Http\Controllers\API\Expense;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Expense\CancelExpenseRequest;
use App\Http\Requests\API\Expense\StoreExpenseRequest;
use App\Http\Requests\API\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Expenses.',
                [],
                403
            );
        }

        $query = Expense::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(
                function (Builder $query) use ($search) {
                    $query
                        ->where(
                            'expense_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'category',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'payee_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'payment_reference',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'receipt_number',
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

        if ($request->filled('category')) {
            $query->where(
                'category',
                $request->category
            );
        }

        if ($request->filled('payment_method')) {
            $query->where(
                'payment_method',
                $request->payment_method
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'expense_date',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'expense_date',
                '<=',
                $request->date_to
            );
        }

        $items = $query
            ->orderByDesc('expense_date')
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
                    fn (Expense $expense) =>
                        $this->data($expense)
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
        ], 'Expenses retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Expense summary.',
                [],
                403
            );
        }

        $recorded = Expense::query()
            ->where(
                'status',
                Expense::STATUS_RECORDED
            );

        return $this->sendResponse([
            'total_expenses' =>
                Expense::count(),

            'draft_expenses' =>
                Expense::where(
                    'status',
                    Expense::STATUS_DRAFT
                )->count(),

            'recorded_expenses' =>
                (clone $recorded)->count(),

            'cancelled_expenses' =>
                Expense::where(
                    'status',
                    Expense::STATUS_CANCELLED
                )->count(),

            'total_recorded_amount' =>
                $this->money(
                    (clone $recorded)
                        ->sum('amount')
                ),

            'today_amount' =>
                $this->money(
                    (clone $recorded)
                        ->whereDate(
                            'expense_date',
                            today()
                        )
                        ->sum('amount')
                ),

            'this_month_amount' =>
                $this->money(
                    (clone $recorded)
                        ->whereYear(
                            'expense_date',
                            now()->year
                        )
                        ->whereMonth(
                            'expense_date',
                            now()->month
                        )
                        ->sum('amount')
                ),

            'currency' => 'RWF',
        ], 'Expense summary retrieved successfully.');
    }

    public function categories(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Expense categories.',
                [],
                403
            );
        }

        $categories = Expense::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        return $this->sendResponse([
            'items' => $categories,
        ], 'Expense categories retrieved successfully.');
    }

    public function store(
        StoreExpenseRequest $request
    ): JsonResponse {
        $this->validatePaymentReference(
            $request->payment_method,
            $request->payment_reference
        );

        $expense = Expense::create([
            'expense_date' =>
                $request->expense_date,

            'category' =>
                trim(
                    $request->category
                ),

            'payee_name' =>
                trim(
                    $request->payee_name
                ),

            'description' =>
                trim(
                    $request->description
                ),

            'amount' =>
                round(
                    (float) $request->amount,
                    2
                ),

            'currency' =>
                'RWF',

            'payment_method' =>
                $request->payment_method,

            'payment_reference' =>
                $request->payment_reference
                    ? trim(
                        $request
                            ->payment_reference
                    )
                    : null,

            'receipt_number' =>
                $request->receipt_number
                    ? trim(
                        $request
                            ->receipt_number
                    )
                    : null,

            'status' =>
                Expense::STATUS_DRAFT,

            'notes' =>
                $request->notes,

            'created_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $expense
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Expense draft created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        Expense $expense
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Expense.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $expense->load(
                    $this->relations()
                )
            ),
            'Expense retrieved successfully.'
        );
    }

    public function update(
        UpdateExpenseRequest $request,
        Expense $expense
    ): JsonResponse {
        if (
            $expense->status !==
            Expense::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Expenses can be updated.',
                [],
                422
            );
        }

        $paymentMethod =
            $request->has(
                'payment_method'
            )
                ? $request->payment_method
                : $expense->payment_method;

        $paymentReference =
            $request->has(
                'payment_reference'
            )
                ? $request->payment_reference
                : $expense->payment_reference;

        $this->validatePaymentReference(
            $paymentMethod,
            $paymentReference
        );

        $expense->update([
            'expense_date' =>
                $request->has(
                    'expense_date'
                )
                    ? $request->expense_date
                    : $expense->expense_date,

            'category' =>
                $request->has('category')
                    ? trim(
                        $request->category
                    )
                    : $expense->category,

            'payee_name' =>
                $request->has(
                    'payee_name'
                )
                    ? trim(
                        $request->payee_name
                    )
                    : $expense->payee_name,

            'description' =>
                $request->has(
                    'description'
                )
                    ? trim(
                        $request->description
                    )
                    : $expense->description,

            'amount' =>
                $request->has('amount')
                    ? round(
                        (float) $request->amount,
                        2
                    )
                    : $expense->amount,

            'payment_method' =>
                $paymentMethod,

            'payment_reference' =>
                $paymentReference
                    ? trim(
                        $paymentReference
                    )
                    : null,

            'receipt_number' =>
                $request->has(
                    'receipt_number'
                )
                    ? (
                        $request->receipt_number
                            ? trim(
                                $request->receipt_number
                            )
                            : null
                    )
                    : $expense->receipt_number,

            'notes' =>
                $request->has('notes')
                    ? $request->notes
                    : $expense->notes,

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $expense
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Expense updated successfully.'
        );
    }

    public function record(
        Request $request,
        Expense $expense
    ): JsonResponse {
        if (!$this->canManage($request->user())) {
            return $this->sendError(
                'You are not allowed to record Expenses.',
                [],
                403
            );
        }

        if (
            $expense->status !==
            Expense::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft Expenses can be recorded.',
                [],
                422
            );
        }

        $this->validatePaymentReference(
            $expense->payment_method,
            $expense->payment_reference
        );

        $expense->update([
            'status' =>
                Expense::STATUS_RECORDED,

            'recorded_by' =>
                $request->user()->id,

            'recorded_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $expense
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Expense recorded successfully.'
        );
    }

    public function cancel(
        CancelExpenseRequest $request,
        Expense $expense
    ): JsonResponse {
        if (
            $expense->source_type ===
            'petty_cash_transaction'
        ) {
            return $this->sendError(
                'Petty Cash expenses must be reversed through the Petty Cash ledger.',
                [],
                422
            );
        }

        if (
            $expense->status ===
            Expense::STATUS_CANCELLED
        ) {
            return $this->sendError(
                'This Expense is already cancelled.',
                [],
                422
            );
        }

        DB::transaction(
            function () use (
                $request,
                $expense
            ) {
                $locked = Expense::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $expense->id
                    );

                if (
                    $locked->status ===
                    Expense::STATUS_CANCELLED
                ) {
                    throw ValidationException::withMessages([
                        'expense' => [
                            'This Expense is already cancelled.',
                        ],
                    ]);
                }

                /*
                 * Module 24 Petty Cash will later protect
                 * linked expenses from direct cancellation
                 * when a cash-ledger reversal is required.
                 */

                $locked->update([
                    'status' =>
                        Expense::STATUS_CANCELLED,

                    'cancelled_by' =>
                        $request->user()->id,

                    'cancelled_at' =>
                        now(),

                    'cancellation_reason' =>
                        trim(
                            $request
                                ->cancellation_reason
                        ),

                    'updated_by' =>
                        $request->user()->id,
                ]);
            }
        );

        return $this->sendResponse(
            $this->data(
                $expense
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Expense cancelled successfully.'
        );
    }

    private function validatePaymentReference(
        string $paymentMethod,
        ?string $paymentReference
    ): void {
        if (
            in_array(
                $paymentMethod,
                [
                    Expense::PAYMENT_MOBILE_MONEY,
                    Expense::PAYMENT_BANK_TRANSFER,
                ],
                true
            ) &&
            blank($paymentReference)
        ) {
            throw ValidationException::withMessages([
                'payment_reference' => [
                    'Payment reference is required for Mobile Money and Bank Transfer expenses.',
                ],
            ]);
        }
    }

    private function canRead(
        ?User $user
    ): bool {
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

    private function canManage(
        ?User $user
    ): bool {
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
            'creator:id,name,email',
            'updater:id,name',
            'recorder:id,name',
            'canceller:id,name',
        ];
    }

    private function data(
        Expense $expense
    ): array {
        return [
            'id' =>
                $expense->id,

            'expense_code' =>
                $expense->expense_code,

            'expense_date' =>
                $expense->expense_date
                    ?->format('Y-m-d'),

            'category' =>
                $expense->category,

            'payee_name' =>
                $expense->payee_name,

            'description' =>
                $expense->description,

            'amount' =>
                $expense->amount,

            'currency' =>
                $expense->currency,

            'payment_method' =>
                $expense->payment_method,

            'payment_reference' =>
                $expense->payment_reference,

            'receipt_number' =>
                $expense->receipt_number,

            'source_type' =>
                $expense->source_type,

            'source_id' =>
                $expense->source_id,

            'status' =>
                $expense->status,

            'notes' =>
                $expense->notes,

            'recorded_at' =>
                $expense->recorded_at
                    ?->toISOString(),

            'cancelled_at' =>
                $expense->cancelled_at
                    ?->toISOString(),

            'cancellation_reason' =>
                $expense->cancellation_reason,

            'creator' =>
                $expense->creator,

            'updater' =>
                $expense->updater,

            'recorder' =>
                $expense->recorder,

            'canceller' =>
                $expense->canceller,
        ];
    }

    private function money(
        $value
    ): string {
        return number_format(
            (float) ($value ?? 0),
            2,
            '.',
            ''
        );
    }
}
