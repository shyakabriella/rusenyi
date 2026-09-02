<?php

namespace App\Http\Controllers\API\Finance;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Finance\CashAllocation\CancelCashAllocationRequest;
use App\Http\Requests\API\Finance\CashAllocation\StoreCashAllocationRequest;
use App\Http\Requests\API\Finance\CashAllocation\UpdateCashAllocationRequest;
use App\Http\Requests\API\Finance\CashAllocation\UploadPaymentProofRequest;
use App\Http\Resources\API\Finance\CashAllocationResource;
use App\Models\Agent;
use App\Models\CashAllocation;
use App\Models\CoffeeSeason;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CashAllocationController extends BaseController
{
    private array $relations = [
        'coffeeSeason',
        'agent.user',
        'creator',
        'updater',
        'approver',
        'canceller',
        'paymentProofUploader',
    ];

    public function index(
        Request $request
    ): JsonResponse {
        $this->authorizeManager(
            $request
        );

        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'status' => [
                'nullable',
                'in:draft,approved,cancelled',
            ],

            'coffee_season_id' => [
                'nullable',
                'integer',
                'exists:coffee_seasons,id',
            ],

            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query =
            CashAllocation::query()
                ->with(
                    $this->relations
                );

        $this->applyFilters(
            $query,
            $request
        );

        $allocations =
            $query
                ->orderByDesc(
                    'allocation_date'
                )
                ->orderByDesc('id')
                ->paginate(
                    min(
                        max(
                            (int)
                            $request->get(
                                'per_page',
                                20
                            ),
                            1
                        ),
                        100
                    )
                );

        return $this->sendResponse(
            [
                'items' =>
                    CashAllocationResource::collection(
                        $allocations
                            ->getCollection()
                    )->resolve(
                        $request
                    ),

                'pagination' => [
                    'current_page' =>
                        $allocations
                            ->currentPage(),

                    'last_page' =>
                        $allocations
                            ->lastPage(),

                    'per_page' =>
                        $allocations
                            ->perPage(),

                    'total' =>
                        $allocations
                            ->total(),

                    'from' =>
                        $allocations
                            ->firstItem(),

                    'to' =>
                        $allocations
                            ->lastItem(),
                ],
            ],
            'Cash allocations retrieved successfully.'
        );
    }

    public function summary(
        Request $request
    ): JsonResponse {
        $this->authorizeManager(
            $request
        );

        $request->validate([
            'coffee_season_id' => [
                'nullable',
                'integer',
                'exists:coffee_seasons,id',
            ],

            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
            ],
        ]);

        $base =
            CashAllocation::query()
                ->when(
                    $request->filled(
                        'coffee_season_id'
                    ),
                    fn ($query) =>
                        $query->where(
                            'coffee_season_id',
                            $request->integer(
                                'coffee_season_id'
                            )
                        )
                )
                ->when(
                    $request->filled(
                        'agent_id'
                    ),
                    fn ($query) =>
                        $query->where(
                            'agent_id',
                            $request->integer(
                                'agent_id'
                            )
                        )
                );

        $totalRecords =
            (clone $base)
                ->count();

        $draftAmount =
            (clone $base)
                ->where(
                    'status',
                    CashAllocation::STATUS_DRAFT
                )
                ->sum('amount');

        $approvedAmount =
            (clone $base)
                ->where(
                    'status',
                    CashAllocation::STATUS_APPROVED
                )
                ->sum('amount');

        $cancelledAmount =
            (clone $base)
                ->where(
                    'status',
                    CashAllocation::STATUS_CANCELLED
                )
                ->sum('amount');

        return $this->sendResponse(
            [
                'currency' =>
                    'RWF',

                'total_records' =>
                    $totalRecords,

                'draft_amount' =>
                    number_format(
                        (float)
                        $draftAmount,
                        2,
                        '.',
                        ''
                    ),

                'approved_amount' =>
                    number_format(
                        (float)
                        $approvedAmount,
                        2,
                        '.',
                        ''
                    ),

                'cancelled_amount' =>
                    number_format(
                        (float)
                        $cancelledAmount,
                        2,
                        '.',
                        ''
                    ),
            ],
            'Cash allocation summary retrieved successfully.'
        );
    }

    public function store(
        StoreCashAllocationRequest $request
    ): JsonResponse {
        $allocation =
            DB::transaction(
                function () use (
                    $request
                ) {
                    $data =
                        $request
                            ->validated();

                    $data[
                        'allocation_code'
                    ] =
                        $this
                            ->generateAllocationCode();

                    $data['currency'] =
                        'RWF';

                    $data['status'] =
                        CashAllocation::STATUS_DRAFT;

                    $data['created_by'] =
                        $request
                            ->user()
                            ->id;

                    return CashAllocation::create(
                        $data
                    );
                }
            );

        $allocation->load(
            $this->relations
        );

        return $this->sendResponse(
            new CashAllocationResource(
                $allocation
            ),
            'Cash allocation created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        CashAllocation $cashAllocation
    ): JsonResponse {
        $this->authorizeManager(
            $request
        );

        $cashAllocation->load(
            $this->relations
        );

        return $this->sendResponse(
            new CashAllocationResource(
                $cashAllocation
            ),
            'Cash allocation retrieved successfully.'
        );
    }

    public function update(
        UpdateCashAllocationRequest $request,
        CashAllocation $cashAllocation
    ): JsonResponse {
        if (
            $cashAllocation->status !==
            CashAllocation::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft cash allocations can be edited.',
                [],
                422
            );
        }

        $data =
            $request
                ->validated();

        $data['updated_by'] =
            $request
                ->user()
                ->id;

        $cashAllocation->update(
            $data
        );

        return $this->sendResponse(
            new CashAllocationResource(
                $cashAllocation
                    ->fresh()
                    ->load(
                        $this->relations
                    )
            ),
            'Cash allocation updated successfully.'
        );
    }

    public function approve(
        Request $request,
        CashAllocation $cashAllocation
    ): JsonResponse {
        $this->authorizeManager(
            $request
        );

        if (
            $cashAllocation->status !==
            CashAllocation::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Only draft cash allocations can be approved.',
                [],
                422
            );
        }

        $cashAllocation->load([
            'agent.user',
            'coffeeSeason',
        ]);

        if (
            !$cashAllocation->agent ||
            $cashAllocation
                ->agent
                ->status !==
                Agent::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Cash allocation cannot be approved because the agent is not active.',
                [],
                422
            );
        }

        if (
            !$cashAllocation
                ->agent
                ->user ||
            $cashAllocation
                ->agent
                ->user
                ->status !==
                'active'
        ) {
            return $this->sendError(
                'Cash allocation cannot be approved because the linked agent user account is not active.',
                [],
                422
            );
        }

        if (
            !$cashAllocation
                ->coffeeSeason ||
            $cashAllocation
                ->coffeeSeason
                ->status !==
                CoffeeSeason::STATUS_ACTIVE
        ) {
            return $this->sendError(
                'Cash allocation cannot be approved because the coffee season is not active.',
                [],
                422
            );
        }

        /*
         * Historical allocations created before the
         * payment-evidence upgrade may have NULL
         * payment_method.
         *
         * Every NEW allocation has a payment method,
         * so proof is mandatory before approval.
         */
        if (
            $cashAllocation->payment_method !== null &&
            !$cashAllocation->payment_proof_path
        ) {
            return $this->sendError(
                'Payment proof is required before this cash allocation can be approved.',
                [],
                422
            );
        }

        if (
            in_array(
                $cashAllocation->payment_method,
                [
                    CashAllocation::PAYMENT_METHOD_MOBILE_MONEY,
                    CashAllocation::PAYMENT_METHOD_BANK_TRANSFER,
                ],
                true
            ) &&
            blank($cashAllocation->reference)
        ) {
            return $this->sendError(
                'Payment reference is required for Mobile Money and Bank Transfer allocations.',
                [],
                422
            );
        }

        $cashAllocation->update([
            'status' =>
                CashAllocation::STATUS_APPROVED,

            'approved_by' =>
                $request
                    ->user()
                    ->id,

            'approved_at' =>
                now(),

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return $this->sendResponse(
            new CashAllocationResource(
                $cashAllocation
                    ->fresh()
                    ->load(
                        $this->relations
                    )
            ),
            'Cash allocation approved successfully.'
        );
    }

    public function uploadProof(
        UploadPaymentProofRequest $request,
        CashAllocation $cashAllocation
    ): JsonResponse {
        if (
            $cashAllocation->status !==
            CashAllocation::STATUS_DRAFT
        ) {
            return $this->sendError(
                'Payment proof can only be changed while the allocation is in draft status.',
                [],
                422
            );
        }

        $file =
            $request->file(
                'payment_proof'
            );

        if (
            $cashAllocation->payment_proof_path
        ) {
            Storage::disk('public')
                ->delete(
                    $cashAllocation
                        ->payment_proof_path
                );
        }

        $path =
            $file->store(
                'cash-allocation-proofs/'
                . $cashAllocation->id,
                'public'
            );

        $cashAllocation->update([
            'payment_proof_path' =>
                $path,

            'payment_proof_original_name' =>
                $file
                    ->getClientOriginalName(),

            'payment_proof_mime_type' =>
                $file
                    ->getMimeType(),

            'payment_proof_size' =>
                $file
                    ->getSize(),

            'payment_proof_uploaded_by' =>
                $request
                    ->user()
                    ->id,

            'payment_proof_uploaded_at' =>
                now(),

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return $this->sendResponse(
            new CashAllocationResource(
                $cashAllocation
                    ->fresh()
                    ->load(
                        $this->relations
                    )
            ),
            'Payment proof uploaded successfully.'
        );
    }

    public function cancel(
        CancelCashAllocationRequest $request,
        CashAllocation $cashAllocation
    ): JsonResponse {
        if (
            $cashAllocation->status ===
            CashAllocation::STATUS_CANCELLED
        ) {
            return $this->sendError(
                'Cash allocation is already cancelled.',
                [],
                422
            );
        }

        $cashAllocation->update([
            'status' =>
                CashAllocation::STATUS_CANCELLED,

            'cancelled_by' =>
                $request
                    ->user()
                    ->id,

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                $request
                    ->validated(
                        'cancellation_reason'
                    ),

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return $this->sendResponse(
            new CashAllocationResource(
                $cashAllocation
                    ->fresh()
                    ->load(
                        $this->relations
                    )
            ),
            'Cash allocation cancelled successfully.'
        );
    }

    private function applyFilters(
        Builder $query,
        Request $request
    ): void {
        $query
            ->when(
                $request->filled(
                    'search'
                ),
                function (
                    Builder $query
                ) use ($request) {
                    $search =
                        trim(
                            (string)
                            $request->search
                        );

                    $query->where(
                        function (
                            Builder $query
                        ) use ($search) {
                            $query
                                ->where(
                                    'allocation_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'reference',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'agent',
                                    function (
                                        Builder $agentQuery
                                    ) use ($search) {
                                        $agentQuery
                                            ->where(
                                                'agent_code',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhereHas(
                                                'user',
                                                function (
                                                    Builder $userQuery
                                                ) use ($search) {
                                                    $userQuery
                                                        ->where(
                                                            'name',
                                                            'like',
                                                            "%{$search}%"
                                                        )
                                                        ->orWhere(
                                                            'phone',
                                                            'like',
                                                            "%{$search}%"
                                                        );
                                                }
                                            );
                                    }
                                );
                        }
                    );
                }
            )

            ->when(
                $request->filled(
                    'status'
                ),
                fn (
                    Builder $query
                ) =>
                    $query->where(
                        'status',
                        $request->status
                    )
            )

            ->when(
                $request->filled(
                    'coffee_season_id'
                ),
                fn (
                    Builder $query
                ) =>
                    $query->where(
                        'coffee_season_id',
                        $request->integer(
                            'coffee_season_id'
                        )
                    )
            )

            ->when(
                $request->filled(
                    'agent_id'
                ),
                fn (
                    Builder $query
                ) =>
                    $query->where(
                        'agent_id',
                        $request->integer(
                            'agent_id'
                        )
                    )
            )

            ->when(
                $request->filled(
                    'date_from'
                ),
                fn (
                    Builder $query
                ) =>
                    $query->whereDate(
                        'allocation_date',
                        '>=',
                        $request->date_from
                    )
            )

            ->when(
                $request->filled(
                    'date_to'
                ),
                fn (
                    Builder $query
                ) =>
                    $query->whereDate(
                        'allocation_date',
                        '<=',
                        $request->date_to
                    )
            );
    }

    private function authorizeManager(
        Request $request
    ): void {
        abort_unless(
            $request->user() &&
            in_array(
                $request
                    ->user()
                    ->role,
                [
                    User::ROLE_ADMIN,
                    User::ROLE_ACCOUNTANT,
                ],
                true
            ),
            403,
            'You are not allowed to manage cash allocations.'
        );
    }

    private function generateAllocationCode(): string
    {
        $prefix = 'CAL-';

        $lastCode =
            CashAllocation::query()
                ->where(
                    'allocation_code',
                    'like',
                    $prefix . '%'
                )
                ->orderByDesc(
                    'allocation_code'
                )
                ->value(
                    'allocation_code'
                );

        $nextNumber = 1;

        if ($lastCode) {
            $nextNumber =
                ((int) substr(
                    $lastCode,
                    strlen($prefix)
                )) + 1;
        }

        do {
            $code =
                $prefix .
                str_pad(
                    (string)
                    $nextNumber,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $exists =
                CashAllocation::query()
                    ->where(
                        'allocation_code',
                        $code
                    )
                    ->exists();

            $nextNumber++;
        } while ($exists);

        return $code;
    }
}
