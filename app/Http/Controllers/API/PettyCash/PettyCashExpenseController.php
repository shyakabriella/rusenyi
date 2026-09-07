<?php

namespace App\Http\Controllers\API\PettyCash;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\PettyCash\ReversePettyCashExpenseRequest;
use App\Http\Requests\API\PettyCash\StorePettyCashExpenseRequest;
use App\Models\PettyCashExpense;
use App\Models\PettyCashTransaction;
use App\Services\PettyCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PettyCashExpenseController extends BaseController
{
    public function __construct(
        private readonly PettyCashService $pettyCash
    ) {
    }

    public function index(
        Request $request
    ): JsonResponse {
        if (
            !in_array(
                $request->user()?->role,
                [
                    'admin',
                    'accountant',
                ],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to view Petty Cash expenses.',
                [],
                403
            );
        }

        $query =
            PettyCashExpense::query()
                ->with([
                    'accountant:id,name,email',
                    'creator:id,name,email',
                    'reverser:id,name,email',
                ])
                ->latest('expense_date')
                ->latest('id');

        if (
            $request->user()->role ===
            'accountant'
        ) {
            $query->where(
                'accountant_id',
                $request->user()->id
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        return $this->sendResponse(
            $query->paginate(
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
            ),
            'Petty Cash expenses retrieved successfully.'
        );
    }

    public function store(
        StorePettyCashExpenseRequest $request
    ): JsonResponse {
        $receiptPath = null;
        $receiptName = null;

        if ($request->hasFile('receipt')) {
            $receipt =
                $request->file('receipt');

            $receiptName =
                $receipt->getClientOriginalName();

            $receiptPath =
                $receipt->store(
                    'petty-cash/receipts',
                    'public'
                );
        }

        try {
            $expense =
                DB::transaction(
                    function () use (
                        $request,
                        $receiptPath,
                        $receiptName
                    ) {
                        $balanceBefore =
                            $this->pettyCash
                                ->lockedBalanceFor(
                                    $request->user()->id
                                );

                        $amount =
                            (float) $request->amount;

                        if (
                            $amount >
                            $balanceBefore
                        ) {
                            throw ValidationException::withMessages([
                                'amount' => [
                                    'Insufficient Petty Cash balance.',
                                ],
                            ]);
                        }

                        $balanceAfter =
                            $balanceBefore -
                            $amount;

                        $expense =
                            PettyCashExpense::create([
                                'accountant_id' =>
                                    $request->user()->id,

                                'expense_date' =>
                                    $request->expense_date,

                                'category' =>
                                    trim(
                                        $request->category
                                    ),

                                'payee' =>
                                    $request->filled('payee')
                                        ? trim(
                                            $request->payee
                                        )
                                        : null,

                                'amount' =>
                                    $amount,

                                'currency' =>
                                    'RWF',

                                'description' =>
                                    trim(
                                        $request->description
                                    ),

                                'receipt_path' =>
                                    $receiptPath,

                                'receipt_name' =>
                                    $receiptName,

                                'status' =>
                                    PettyCashExpense::STATUS_POSTED,

                                'created_by' =>
                                    $request->user()->id,
                            ]);

                        PettyCashTransaction::create([
                            'accountant_id' =>
                                $request->user()->id,

                            'transaction_date' =>
                                $request->expense_date,

                            'transaction_type' =>
                                PettyCashTransaction::TYPE_DEBIT,

                            'amount' =>
                                $amount,

                            'balance_before' =>
                                $balanceBefore,

                            'balance_after' =>
                                $balanceAfter,

                            'currency' =>
                                'RWF',

                            'category' =>
                                $expense->category,

                            'counterparty_name' =>
                                $expense->payee
                                ?? 'Petty Cash Expense',

                            'purpose' =>
                                $expense->description,

                            'reference_number' =>
                                $expense->expense_code,

                            'reference_type' =>
                                'petty_cash_expense',

                            'reference_id' =>
                                $expense->id,

                            'status' =>
                                PettyCashTransaction::STATUS_POSTED,

                            'posted_by' =>
                                $request->user()->id,

                            'posted_at' =>
                                now(),
                        ]);

                        return $expense;
                    }
                );
        } catch (Throwable $error) {
            if ($receiptPath) {
                Storage::disk('public')
                    ->delete(
                        $receiptPath
                    );
            }

            throw $error;
        }

        return $this->sendResponse([
            'expense' =>
                $this->expenseData(
                    $expense->fresh()
                ),

            'balance' =>
                number_format(
                    $this->pettyCash->balance(
                        $request->user()->id
                    ),
                    2,
                    '.',
                    ''
                ),

            'currency' =>
                'RWF',
        ], 'Petty Cash expense recorded successfully.', 201);
    }

    public function reverse(
        ReversePettyCashExpenseRequest $request,
        PettyCashExpense $pettyCashExpense
    ): JsonResponse {
        $expense =
            DB::transaction(
                function () use (
                    $request,
                    $pettyCashExpense
                ) {
                    $expense =
                        PettyCashExpense::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $pettyCashExpense->id
                            );

                    if (
                        $expense->status ===
                        PettyCashExpense::STATUS_REVERSED
                    ) {
                        throw ValidationException::withMessages([
                            'status' => [
                                'This Petty Cash expense has already been reversed.',
                            ],
                        ]);
                    }

                    $originalTransaction =
                        PettyCashTransaction::query()
                            ->where(
                                'reference_type',
                                'petty_cash_expense'
                            )
                            ->where(
                                'reference_id',
                                $expense->id
                            )
                            ->where(
                                'transaction_type',
                                PettyCashTransaction::TYPE_DEBIT
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $balanceBefore =
                        $this->pettyCash
                            ->lockedBalanceFor(
                                $expense->accountant_id
                            );

                    $amount =
                        (float) $expense->amount;

                    $balanceAfter =
                        $balanceBefore +
                        $amount;

                    PettyCashTransaction::create([
                        'accountant_id' =>
                            $expense->accountant_id,

                        'transaction_date' =>
                            now()->toDateString(),

                        'transaction_type' =>
                            PettyCashTransaction::TYPE_REVERSAL,

                        'amount' =>
                            $amount,

                        'balance_before' =>
                            $balanceBefore,

                        'balance_after' =>
                            $balanceAfter,

                        'currency' =>
                            'RWF',

                        'category' =>
                            $expense->category,

                        'counterparty_name' =>
                            $expense->payee
                            ?? 'Petty Cash Expense',

                        'purpose' =>
                            "Reversal of {$expense->expense_code}",

                        'reference_number' =>
                            $expense->expense_code,

                        'reference_type' =>
                            'petty_cash_expense',

                        'reference_id' =>
                            $expense->id,

                        'reverses_transaction_id' =>
                            $originalTransaction->id,

                        'status' =>
                            PettyCashTransaction::STATUS_POSTED,

                        'notes' =>
                            trim(
                                $request->reason
                            ),

                        'posted_by' =>
                            $request->user()->id,

                        'posted_at' =>
                            now(),
                    ]);

                    $originalTransaction->update([
                        'status' =>
                            PettyCashTransaction::STATUS_REVERSED,

                        'reversed_by' =>
                            $request->user()->id,

                        'reversed_at' =>
                            now(),

                        'reversal_reason' =>
                            trim(
                                $request->reason
                            ),
                    ]);

                    $expense->update([
                        'status' =>
                            PettyCashExpense::STATUS_REVERSED,

                        'reversed_by' =>
                            $request->user()->id,

                        'reversed_at' =>
                            now(),

                        'reversal_reason' =>
                            trim(
                                $request->reason
                            ),
                    ]);

                    return $expense;
                }
            );

        return $this->sendResponse([
            'expense' =>
                $this->expenseData(
                    $expense->fresh()
                ),

            'balance' =>
                number_format(
                    $this->pettyCash->balance(
                        $expense->accountant_id
                    ),
                    2,
                    '.',
                    ''
                ),

            'currency' =>
                'RWF',
        ], 'Petty Cash expense reversed successfully.');
    }

    private function expenseData(
        PettyCashExpense $expense
    ): array {
        return [
            'id' =>
                $expense->id,

            'expense_code' =>
                $expense->expense_code,

            'accountant_id' =>
                $expense->accountant_id,

            'expense_date' =>
                $expense->expense_date
                    ?->format('Y-m-d'),

            'category' =>
                $expense->category,

            'payee' =>
                $expense->payee,

            'amount' =>
                $expense->amount,

            'currency' =>
                $expense->currency,

            'description' =>
                $expense->description,

            'status' =>
                $expense->status,

            'receipt' => [
                'exists' =>
                    (bool) $expense->receipt_path,

                'name' =>
                    $expense->receipt_name,

                'url' =>
                    $expense->receipt_path
                        ? Storage::disk('public')
                            ->url(
                                $expense->receipt_path
                            )
                        : null,
            ],

            'created_at' =>
                $expense->created_at
                    ?->toISOString(),
        ];
    }
}
