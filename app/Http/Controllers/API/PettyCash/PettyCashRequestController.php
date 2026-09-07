<?php

namespace App\Http\Controllers\API\PettyCash;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\PettyCash\CancelPettyCashRequest;
use App\Http\Requests\API\PettyCash\RejectPettyCashRequest;
use App\Http\Requests\API\PettyCash\StorePettyCashRequest;
use App\Models\PettyCashRequest;
use App\Models\PettyCashTransaction;
use App\Services\PettyCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashRequestController extends BaseController
{
    public function __construct(
        private readonly PettyCashService $pettyCash
    ) {
    }

    public function summary(
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
                'You are not allowed to view Petty Cash summary.',
                [],
                403
            );
        }

        $accountantId =
            $request->user()->role === 'accountant'
                ? $request->user()->id
                : (
                    $request->filled('accountant_id')
                        ? (int) $request->accountant_id
                        : null
                );

        $requests =
            PettyCashRequest::query();

        if ($accountantId) {
            $requests->where(
                'requested_by',
                $accountantId
            );
        }

        if ($accountantId) {
            $balance =
                $this->budget->balanceFor(
                    $accountantId
                );
        } else {
            $latestTransactions =
                PettyCashTransaction::query()
                    ->whereNotNull(
                        'accountant_id'
                    )
                    ->where(
                        'status',
                        'posted'
                    )
                    ->orderByDesc('id')
                    ->get([
                        'accountant_id',
                        'balance_after',
                    ])
                    ->unique(
                        'accountant_id'
                    );

            $balance =
                (float) $latestTransactions
                    ->sum(
                        fn ($item) =>
                            (float) $item->balance_after
                    );
        }

        return $this->sendResponse([
            'currency' =>
                'RWF',

            'balance' =>
                number_format(
                    $balance,
                    2,
                    '.',
                    ''
                ),

            'total_requests' =>
                (clone $requests)
                    ->count(),

            'pending_requests' =>
                (clone $requests)
                    ->where(
                        'status',
                        PettyCashRequest::STATUS_PENDING
                    )
                    ->count(),

            'approved_requests' =>
                (clone $requests)
                    ->where(
                        'status',
                        PettyCashRequest::STATUS_APPROVED
                    )
                    ->count(),

            'approved_amount' =>
                number_format(
                    (float) (
                        (clone $requests)
                            ->where(
                                'status',
                                PettyCashRequest::STATUS_APPROVED
                            )
                            ->sum('amount')
                    ),
                    2,
                    '.',
                    ''
                ),

            'rejected_requests' =>
                (clone $requests)
                    ->where(
                        'status',
                        PettyCashRequest::STATUS_REJECTED
                    )
                    ->count(),

            'cancelled_requests' =>
                (clone $requests)
                    ->where(
                        'status',
                        PettyCashRequest::STATUS_CANCELLED
                    )
                    ->count(),
        ], 'Petty Cash summary retrieved successfully.');
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
                'You are not allowed to view Petty Cash requests.',
                [],
                403
            );
        }

        $query =
            PettyCashRequest::query()
                ->with([
                    'requester:id,name,email',
                    'approver:id,name,email',
                    'rejecter:id,name,email',
                    'canceller:id,name,email',
                ])
                ->latest();

        if (
            $request->user()->role ===
            'accountant'
        ) {
            $query->where(
                'requested_by',
                $request->user()->id
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('search')) {
            $search =
                trim(
                    (string) $request->search
                );

            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'request_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'purpose',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        return $this->sendResponse(
            $query->paginate(
                min(
                    max(
                        (int) $request->get(
                            'per_page',
                            15
                        ),
                        1
                    ),
                    100
                )
            ),
            'Petty Cash requests retrieved successfully.'
        );
    }

    public function store(
        StorePettyCashRequest $request
    ): JsonResponse {
        $item =
            PettyCashRequest::create([
                'requested_by' =>
                    $request->user()->id,

                'amount' =>
                    $request->amount,

                'currency' =>
                    'RWF',

                'purpose' =>
                    trim(
                        $request->purpose
                    ),

                'status' =>
                    PettyCashRequest::STATUS_PENDING,
            ]);

        return $this->sendResponse(
            $item
                ->fresh()
                ->load(
                    'requester:id,name,email'
                ),
            'Petty Cash budget request submitted successfully.',
            201
        );
    }

    public function show(
        Request $request,
        PettyCashRequest $pettyCashRequest
    ): JsonResponse {
        if (
            $request->user()->role !==
                'admin' &&
            (
                $request->user()->role !==
                    'accountant' ||
                $pettyCashRequest->requested_by !==
                    $request->user()->id
            )
        ) {
            return $this->sendError(
                'You are not allowed to view this Petty Cash request.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $pettyCashRequest->load([
                'requester:id,name,email',
                'approver:id,name,email',
                'rejecter:id,name,email',
                'canceller:id,name,email',
            ]),
            'Petty Cash request retrieved successfully.'
        );
    }

    public function approve(
        Request $request,
        PettyCashRequest $pettyCashRequest
    ): JsonResponse {
        if (
            $request->user()?->role !==
            'admin'
        ) {
            return $this->sendError(
                'Only Admin can approve Petty Cash requests.',
                [],
                403
            );
        }

        $item =
            DB::transaction(
                function () use (
                    $request,
                    $pettyCashRequest
                ) {
                    $item =
                        PettyCashRequest::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $pettyCashRequest->id
                            );

                    if (
                        $item->status !==
                        PettyCashRequest::STATUS_PENDING
                    ) {
                        throw ValidationException::withMessages([
                            'status' => [
                                'Only pending Petty Cash requests can be approved.',
                            ],
                        ]);
                    }

                    $alreadyCredited =
                        PettyCashTransaction::query()
                            ->where(
                                'reference_type',
                                'petty_cash_request'
                            )
                            ->where(
                                'reference_id',
                                $item->id
                            )
                            ->where(
                                'transaction_type',
                                PettyCashTransaction::TYPE_CREDIT
                            )
                            ->exists();

                    if ($alreadyCredited) {
                        throw ValidationException::withMessages([
                            'status' => [
                                'This Petty Cash request has already credited the Accountant balance.',
                            ],
                        ]);
                    }

                    $balanceBefore =
                        $this->pettyCash
                            ->lockedBalanceFor(
                                $item->requested_by
                            );

                    $amount =
                        (float) $item->amount;

                    $balanceAfter =
                        $balanceBefore +
                        $amount;

                    $accountantName =
                        $item
                            ->requester()
                            ->value('name')
                        ?? 'Accountant';

                    PettyCashTransaction::create([
                        'accountant_id' =>
                            $item->requested_by,

                        'transaction_date' =>
                            now()->toDateString(),

                        'transaction_type' =>
                            PettyCashTransaction::TYPE_CREDIT,

                        'amount' =>
                            $amount,

                        'balance_before' =>
                            $balanceBefore,

                        'balance_after' =>
                            $balanceAfter,

                        'currency' =>
                            'RWF',

                        'category' =>
                            'budget_funding',

                        'counterparty_name' =>
                            $accountantName,

                        'purpose' =>
                            "Approved Petty Cash budget {$item->request_code}: {$item->purpose}",

                        'reference_number' =>
                            $item->request_code,

                        'reference_type' =>
                            'petty_cash_request',

                        'reference_id' =>
                            $item->id,

                        'status' =>
                            PettyCashTransaction::STATUS_POSTED,

                        'posted_by' =>
                            $request->user()->id,

                        'posted_at' =>
                            now(),
                    ]);

                    $item->update([
                        'status' =>
                            PettyCashRequest::STATUS_APPROVED,

                        'approved_by' =>
                            $request->user()->id,

                        'approved_at' =>
                            now(),
                    ]);

                    return $item;
                }
            );

        return $this->sendResponse([
            'request' =>
                $item->fresh()->load([
                    'requester:id,name,email',
                    'approver:id,name,email',
                ]),

            'balance' =>
                number_format(
                    $this->pettyCash->balance(
                        $item->requested_by
                    ),
                    2,
                    '.',
                    ''
                ),

            'currency' =>
                'RWF',
        ], 'Petty Cash request approved and Accountant balance credited successfully.');
    }

    public function reject(
        RejectPettyCashRequest $request,
        PettyCashRequest $pettyCashRequest
    ): JsonResponse {
        $item =
            DB::transaction(
                function () use (
                    $request,
                    $pettyCashRequest
                ) {
                    $item =
                        PettyCashRequest::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $pettyCashRequest->id
                            );

                    if (
                        $item->status !==
                        PettyCashRequest::STATUS_PENDING
                    ) {
                        throw ValidationException::withMessages([
                            'status' => [
                                'Only pending requests can be rejected.',
                            ],
                        ]);
                    }

                    $item->update([
                        'status' =>
                            PettyCashRequest::STATUS_REJECTED,

                        'rejected_by' =>
                            $request->user()->id,

                        'rejected_at' =>
                            now(),

                        'rejection_reason' =>
                            trim(
                                $request->reason
                            ),
                    ]);

                    return $item;
                }
            );

        return $this->sendResponse(
            $item->fresh(),
            'Petty Cash request rejected successfully.'
        );
    }

    public function cancel(
        CancelPettyCashRequest $request,
        PettyCashRequest $pettyCashRequest
    ): JsonResponse {
        if (
            $pettyCashRequest->requested_by !==
            $request->user()->id
        ) {
            return $this->sendError(
                'You can only cancel your own Petty Cash request.',
                [],
                403
            );
        }

        if (
            $pettyCashRequest->status !==
            PettyCashRequest::STATUS_PENDING
        ) {
            return $this->sendError(
                'Only pending Petty Cash requests can be cancelled.',
                [],
                422
            );
        }

        $pettyCashRequest->update([
            'status' =>
                PettyCashRequest::STATUS_CANCELLED,

            'cancelled_by' =>
                $request->user()->id,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                trim(
                    $request->reason
                ),
        ]);

        return $this->sendResponse(
            $pettyCashRequest->fresh(),
            'Petty Cash request cancelled successfully.'
        );
    }
}
