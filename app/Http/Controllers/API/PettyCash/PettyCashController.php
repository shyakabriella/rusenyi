<?php

namespace App\Http\Controllers\API\PettyCash;

use App\Http\Controllers\API\BaseController;
use App\Models\PettyCashExpense;
use App\Models\PettyCashRequest;
use App\Models\PettyCashTransaction;
use App\Services\PettyCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PettyCashController extends BaseController
{
    public function __construct(
        private readonly PettyCashService $pettyCash
    ) {
    }

    public function dashboard(
        Request $request
    ): JsonResponse {
        if (!$this->canRead($request)) {
            return $this->sendError(
                'You are not allowed to view Petty Cash.',
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

        $expenses =
            PettyCashExpense::query();

        if ($accountantId) {
            $requests->where(
                'requested_by',
                $accountantId
            );

            $expenses->where(
                'accountant_id',
                $accountantId
            );
        }

        return $this->sendResponse([
            'currency' =>
                'RWF',

            'balance' =>
                number_format(
                    $this->pettyCash->balance(
                        $accountantId
                    ),
                    2,
                    '.',
                    ''
                ),

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

            'approved_funding' =>
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

            'expenses' =>
                number_format(
                    (float) (
                        (clone $expenses)
                            ->where(
                                'status',
                                PettyCashExpense::STATUS_POSTED
                            )
                            ->sum('amount')
                    ),
                    2,
                    '.',
                    ''
                ),
        ], 'Petty Cash dashboard retrieved successfully.');
    }

    public function transactions(
        Request $request
    ): JsonResponse {
        if (!$this->canRead($request)) {
            return $this->sendError(
                'You are not allowed to view Petty Cash transactions.',
                [],
                403
            );
        }

        $query =
            PettyCashTransaction::query()
                ->with([
                    'accountant:id,name,email',
                    'recorder:id,name,email',
                ])
                ->latest();

        if (
            $request->user()->role ===
            'accountant'
        ) {
            $query->where(
                'accountant_id',
                $request->user()->id
            );
        }

        if (
            $request->user()->role ===
                'admin' &&
            $request->filled('accountant_id')
        ) {
            $query->where(
                'accountant_id',
                (int) $request->accountant_id
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
            'Petty Cash transactions retrieved successfully.'
        );
    }

    private function canRead(
        Request $request
    ): bool {
        return in_array(
            $request->user()?->role,
            [
                'admin',
                'accountant',
            ],
            true
        );
    }
}
