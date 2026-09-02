<?php

namespace App\Http\Controllers\API\PettyCash;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\PettyCash\ReversePettyCashTransactionRequest;
use App\Http\Requests\API\PettyCash\StorePettyCashTransactionRequest;
use App\Models\Expense;
use App\Models\PettyCashTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Petty Cash.',
                [],
                403
            );
        }

        $query = PettyCashTransaction::query()
            ->with($this->relations());

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->search
            );

            $query->where(
                function (Builder $query) use ($search) {
                    $query
                        ->where(
                            'transaction_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'category',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'counterparty_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'purpose',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'reference_number',
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

        if ($request->filled('transaction_type')) {
            $query->where(
                'transaction_type',
                $request->transaction_type
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

        if ($request->filled('date_from')) {
            $query->whereDate(
                'transaction_date',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'transaction_date',
                '<=',
                $request->date_to
            );
        }

        $items = $query
            ->orderByDesc('transaction_date')
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
                    fn (PettyCashTransaction $transaction) =>
                        $this->data($transaction)
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
        ], 'Petty Cash transactions retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Petty Cash summary.',
                [],
                403
            );
        }

        $latest =
            PettyCashTransaction::query()
                ->latest('id')
                ->first();

        $funded =
            PettyCashTransaction::query()
                ->where(
                    'transaction_type',
                    PettyCashTransaction::TYPE_FUND_IN
                )
                ->where(
                    'status',
                    PettyCashTransaction::STATUS_POSTED
                );

        $spent =
            PettyCashTransaction::query()
                ->where(
                    'transaction_type',
                    PettyCashTransaction::TYPE_EXPENSE
                )
                ->where(
                    'status',
                    PettyCashTransaction::STATUS_POSTED
                );

        return $this->sendResponse([
            'current_balance' =>
                $this->money(
                    $latest?->balance_after ?? 0
                ),

            'total_funded' =>
                $this->money(
                    (clone $funded)
                        ->sum('amount')
                ),

            'total_spent' =>
                $this->money(
                    (clone $spent)
                        ->sum('amount')
                ),

            'today_spent' =>
                $this->money(
                    (clone $spent)
                        ->whereDate(
                            'transaction_date',
                            today()
                        )
                        ->sum('amount')
                ),

            'this_month_spent' =>
                $this->money(
                    (clone $spent)
                        ->whereYear(
                            'transaction_date',
                            now()->year
                        )
                        ->whereMonth(
                            'transaction_date',
                            now()->month
                        )
                        ->sum('amount')
                ),

            'funding_transactions' =>
                (clone $funded)->count(),

            'expense_transactions' =>
                (clone $spent)->count(),

            'reversal_transactions' =>
                PettyCashTransaction::query()
                    ->where(
                        'transaction_type',
                        PettyCashTransaction::TYPE_REVERSAL
                    )
                    ->count(),

            'currency' => 'RWF',
        ], 'Petty Cash summary retrieved successfully.');
    }

    public function store(
        StorePettyCashTransactionRequest $request
    ): JsonResponse {
        $transaction = DB::transaction(
            function () use ($request) {
                $latest =
                    PettyCashTransaction::query()
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                $before = round(
                    (float) (
                        $latest?->balance_after ??
                        0
                    ),
                    2
                );

                $amount = round(
                    (float) $request->amount,
                    2
                );

                $type =
                    (string) $request
                        ->transaction_type;

                if (
                    $type ===
                    PettyCashTransaction::TYPE_EXPENSE
                ) {
                    if (
                        blank(
                            $request->category
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'category' => [
                                'Expense category is required for a Petty Cash Expense.',
                            ],
                        ]);
                    }

                    if ($amount > $before) {
                        throw ValidationException::withMessages([
                            'amount' => [
                                'Petty Cash amount exceeds the available balance.',
                            ],
                        ]);
                    }

                    $after =
                        $before - $amount;
                } else {
                    $after =
                        $before + $amount;
                }

                $transaction =
                    PettyCashTransaction::create([
                        'transaction_date' =>
                            $request
                                ->transaction_date,

                        'transaction_type' =>
                            $type,

                        'amount' =>
                            $amount,

                        'balance_before' =>
                            $before,

                        'balance_after' =>
                            $after,

                        'currency' =>
                            'RWF',

                        'category' =>
                            $request->category
                                ? trim(
                                    $request->category
                                )
                                : null,

                        'counterparty_name' =>
                            trim(
                                $request
                                    ->counterparty_name
                            ),

                        'purpose' =>
                            trim(
                                $request->purpose
                            ),

                        'reference_number' =>
                            $request
                                ->reference_number
                                ? trim(
                                    $request
                                        ->reference_number
                                )
                                : null,

                        'receipt_number' =>
                            $request
                                ->receipt_number
                                ? trim(
                                    $request
                                        ->receipt_number
                                )
                                : null,

                        'status' =>
                            PettyCashTransaction::STATUS_POSTED,

                        'notes' =>
                            $request->notes,

                        'posted_by' =>
                            $request->user()->id,

                        'posted_at' =>
                            now(),
                    ]);

                if (
                    $type ===
                    PettyCashTransaction::TYPE_EXPENSE
                ) {
                    $expense =
                        Expense::create([
                            'expense_date' =>
                                $request
                                    ->transaction_date,

                            'category' =>
                                trim(
                                    $request->category
                                ),

                            'payee_name' =>
                                trim(
                                    $request
                                        ->counterparty_name
                                ),

                            'description' =>
                                trim(
                                    $request->purpose
                                ),

                            'amount' =>
                                $amount,

                            'currency' =>
                                'RWF',

                            'payment_method' =>
                                Expense::PAYMENT_CASH,

                            'payment_reference' =>
                                $request
                                    ->reference_number
                                    ? trim(
                                        $request
                                            ->reference_number
                                    )
                                    : null,

                            'receipt_number' =>
                                $request
                                    ->receipt_number
                                    ? trim(
                                        $request
                                            ->receipt_number
                                    )
                                    : null,

                            'source_type' =>
                                'petty_cash_transaction',

                            'source_id' =>
                                $transaction->id,

                            'status' =>
                                Expense::STATUS_RECORDED,

                            'notes' =>
                                $request->notes,

                            'created_by' =>
                                $request->user()->id,

                            'recorded_by' =>
                                $request->user()->id,

                            'recorded_at' =>
                                now(),
                        ]);

                    $transaction->update([
                        'expense_id' =>
                            $expense->id,
                    ]);
                }

                return $transaction;
            }
        );

        return $this->sendResponse(
            $this->data(
                $transaction
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            $transaction->transaction_type ===
                PettyCashTransaction::TYPE_EXPENSE
                ? 'Petty Cash Expense posted and Expense record created successfully.'
                : 'Petty Cash funding posted successfully.',
            201
        );
    }

    public function show(
        Request $request,
        PettyCashTransaction $pettyCashTransaction
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Petty Cash transaction.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $pettyCashTransaction
                    ->load(
                        $this->relations()
                    )
            ),
            'Petty Cash transaction retrieved successfully.'
        );
    }

    public function reverse(
        ReversePettyCashTransactionRequest $request,
        PettyCashTransaction $pettyCashTransaction
    ): JsonResponse {
        if (
            $pettyCashTransaction->transaction_type ===
            PettyCashTransaction::TYPE_REVERSAL
        ) {
            return $this->sendError(
                'A reversal transaction cannot be reversed.',
                [],
                422
            );
        }

        if (
            $pettyCashTransaction->status !==
            PettyCashTransaction::STATUS_POSTED
        ) {
            return $this->sendError(
                'Only posted Petty Cash transactions can be reversed.',
                [],
                422
            );
        }

        $reversal = DB::transaction(
            function () use (
                $request,
                $pettyCashTransaction
            ) {
                $original =
                    PettyCashTransaction::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $pettyCashTransaction->id
                        );

                if (
                    $original->status !==
                    PettyCashTransaction::STATUS_POSTED
                ) {
                    throw ValidationException::withMessages([
                        'transaction' => [
                            'This Petty Cash transaction is no longer posted.',
                        ],
                    ]);
                }

                $latestActiveOriginal =
                    PettyCashTransaction::query()
                        ->where(
                            'status',
                            PettyCashTransaction::STATUS_POSTED
                        )
                        ->where(
                            'transaction_type',
                            '!=',
                            PettyCashTransaction::TYPE_REVERSAL
                        )
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                if (
                    !$latestActiveOriginal ||
                    $latestActiveOriginal->id !==
                    $original->id
                ) {
                    throw ValidationException::withMessages([
                        'transaction' => [
                            'Only the latest active Petty Cash transaction can be reversed.',
                        ],
                    ]);
                }

                $latest =
                    PettyCashTransaction::query()
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                $before = round(
                    (float) (
                        $latest?->balance_after ??
                        0
                    ),
                    2
                );

                $amount = round(
                    (float) $original->amount,
                    2
                );

                if (
                    $original->transaction_type ===
                    PettyCashTransaction::TYPE_FUND_IN
                ) {
                    if ($amount > $before) {
                        throw ValidationException::withMessages([
                            'transaction' => [
                                'This funding cannot be reversed because the current Petty Cash balance is lower than the funding amount.',
                            ],
                        ]);
                    }

                    $after =
                        $before - $amount;
                } else {
                    $after =
                        $before + $amount;
                }

                $reversal =
                    PettyCashTransaction::create([
                        'transaction_date' =>
                            now()->toDateString(),

                        'transaction_type' =>
                            PettyCashTransaction::TYPE_REVERSAL,

                        'amount' =>
                            $amount,

                        'balance_before' =>
                            $before,

                        'balance_after' =>
                            $after,

                        'currency' =>
                            'RWF',

                        'category' =>
                            $original->category,

                        'counterparty_name' =>
                            $original
                                ->counterparty_name,

                        'purpose' =>
                            'Reversal of ' .
                            $original
                                ->transaction_code,

                        'reference_number' =>
                            $original
                                ->reference_number,

                        'receipt_number' =>
                            $original
                                ->receipt_number,

                        'reverses_transaction_id' =>
                            $original->id,

                        'status' =>
                            PettyCashTransaction::STATUS_POSTED,

                        'notes' =>
                            trim(
                                $request
                                    ->reversal_reason
                            ),

                        'posted_by' =>
                            $request->user()->id,

                        'posted_at' =>
                            now(),
                    ]);

                if (
                    $original->transaction_type ===
                        PettyCashTransaction::TYPE_EXPENSE &&
                    $original->expense_id
                ) {
                    $expense =
                        Expense::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $original
                                    ->expense_id
                            );

                    if (
                        $expense->status !==
                        Expense::STATUS_RECORDED
                    ) {
                        throw ValidationException::withMessages([
                            'transaction' => [
                                'The linked Expense is not in recorded status and cannot be reversed safely.',
                            ],
                        ]);
                    }

                    $expense->update([
                        'status' =>
                            Expense::STATUS_CANCELLED,

                        'cancelled_by' =>
                            $request->user()->id,

                        'cancelled_at' =>
                            now(),

                        'cancellation_reason' =>
                            trim(
                                $request
                                    ->reversal_reason
                            ),

                        'updated_by' =>
                            $request->user()->id,
                    ]);
                }

                $original->update([
                    'status' =>
                        PettyCashTransaction::STATUS_REVERSED,

                    'reversed_by' =>
                        $request->user()->id,

                    'reversed_at' =>
                        now(),

                    'reversal_reason' =>
                        trim(
                            $request
                                ->reversal_reason
                        ),
                ]);

                return $reversal;
            }
        );

        return $this->sendResponse(
            $this->data(
                $reversal
                    ->fresh()
                    ->load(
                        $this->relations()
                    )
            ),
            'Petty Cash transaction reversed successfully.'
        );
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
            'expense',
            'poster:id,name,email',
            'reverser:id,name',
            'reversedTransaction',
        ];
    }

    private function data(
        PettyCashTransaction $transaction
    ): array {
        return [
            'id' =>
                $transaction->id,

            'transaction_code' =>
                $transaction
                    ->transaction_code,

            'transaction_date' =>
                $transaction
                    ->transaction_date
                    ?->format('Y-m-d'),

            'transaction_type' =>
                $transaction
                    ->transaction_type,

            'amount' =>
                $transaction->amount,

            'balance_before' =>
                $transaction
                    ->balance_before,

            'balance_after' =>
                $transaction
                    ->balance_after,

            'currency' =>
                $transaction->currency,

            'category' =>
                $transaction->category,

            'counterparty_name' =>
                $transaction
                    ->counterparty_name,

            'purpose' =>
                $transaction->purpose,

            'reference_number' =>
                $transaction
                    ->reference_number,

            'receipt_number' =>
                $transaction
                    ->receipt_number,

            'expense_id' =>
                $transaction->expense_id,

            'reverses_transaction_id' =>
                $transaction
                    ->reverses_transaction_id,

            'status' =>
                $transaction->status,

            'notes' =>
                $transaction->notes,

            'posted_at' =>
                $transaction
                    ->posted_at
                    ?->toISOString(),

            'reversed_at' =>
                $transaction
                    ->reversed_at
                    ?->toISOString(),

            'reversal_reason' =>
                $transaction
                    ->reversal_reason,

            'expense' =>
                $transaction->expense,

            'poster' =>
                $transaction->poster,

            'reverser' =>
                $transaction->reverser,

            'reversed_transaction' =>
                $transaction
                    ->reversedTransaction,
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
