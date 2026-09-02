<?php

namespace App\Http\Controllers\API\Finance;

use App\Http\Controllers\API\BaseController;
use App\Models\Agent;
use App\Models\AgentWalletTransaction;
use App\Models\User;
use App\Services\Finance\AgentWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentWalletController extends BaseController
{
    public function index(
        Request $request,
        AgentWalletService $wallet
    ): JsonResponse {
        if (!$this->canManageWallets($request->user())) {
            return $this->sendError(
                'You are not allowed to view agent wallets.',
                [],
                403
            );
        }

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'coffee_season_id' => [
                'nullable',
                'integer',
                'exists:coffee_seasons,id',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $seasonId = $request->filled('coffee_season_id')
            ? $request->integer('coffee_season_id')
            : null;

        $agents = Agent::query()
            ->with('user')
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = trim((string) $request->search);

                    $query->where(function ($q) use ($search) {
                        $q->where('agent_code', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                }
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->status)
            )
            ->orderBy('agent_code')
            ->paginate(
                min(max((int) $request->get('per_page', 20), 1), 100)
            );

        $items = $agents->getCollection()
            ->map(function (Agent $agent) use ($wallet, $seasonId) {
                return [
                    'agent' => $this->agentData($agent),
                    'summary' => $wallet->summary(
                        $agent->id,
                        $seasonId
                    ),
                ];
            })
            ->values();

        return $this->sendResponse([
            'items' => $items,
            'pagination' => $this->pagination($agents),
        ], 'Agent wallets retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        if (!$this->canManageWallets($request->user())) {
            return $this->sendError(
                'You are not allowed to view agent wallets.',
                [],
                403
            );
        }

        $request->validate([
            'coffee_season_id' => [
                'nullable',
                'integer',
                'exists:coffee_seasons,id',
            ],
        ]);

        $query = AgentWalletTransaction::query();

        if ($request->filled('coffee_season_id')) {
            $query->where(
                'coffee_season_id',
                $request->integer('coffee_season_id')
            );
        }

        $credits = (clone $query)
            ->where(
                'direction',
                AgentWalletTransaction::DIRECTION_CREDIT
            )
            ->sum('amount');

        $debits = (clone $query)
            ->where(
                'direction',
                AgentWalletTransaction::DIRECTION_DEBIT
            )
            ->sum('amount');

        $allocated = (clone $query)
            ->where(
                'type',
                AgentWalletTransaction::TYPE_CASH_ALLOCATION
            )
            ->sum('amount');

        $reversed = (clone $query)
            ->where(
                'type',
                AgentWalletTransaction::TYPE_CASH_ALLOCATION_REVERSAL
            )
            ->sum('amount');

        $spent = (clone $query)
            ->where(
                'type',
                AgentWalletTransaction::TYPE_COFFEE_PURCHASE
            )
            ->sum('amount');

        return $this->sendResponse([
            'total_allocated' => $this->money($allocated),
            'total_reversed' => $this->money($reversed),
            'total_spent' => $this->money($spent),
            'available_balance' => $this->money(
                (float) $credits - (float) $debits
            ),
            'agents_with_activity' => (clone $query)
                ->distinct()
                ->count('agent_id'),
            'currency' => 'RWF',
        ], 'Agent wallet summary retrieved successfully.');
    }

    public function show(
        Request $request,
        Agent $agent,
        AgentWalletService $wallet
    ): JsonResponse {
        if (!$this->canManageWallets($request->user())) {
            return $this->sendError(
                'You are not allowed to view this wallet.',
                [],
                403
            );
        }

        $request->validate([
            'coffee_season_id' => [
                'nullable',
                'integer',
                'exists:coffee_seasons,id',
            ],
        ]);

        $seasonId = $request->filled('coffee_season_id')
            ? $request->integer('coffee_season_id')
            : null;

        $agent->load('user');

        return $this->sendResponse([
            'agent' => $this->agentData($agent),
            'summary' => $wallet->summary(
                $agent->id,
                $seasonId
            ),
        ], 'Agent wallet retrieved successfully.');
    }

    public function transactions(
        Request $request,
        Agent $agent
    ): JsonResponse {
        if (!$this->canManageWallets($request->user())) {
            return $this->sendError(
                'You are not allowed to view wallet transactions.',
                [],
                403
            );
        }

        return $this->transactionResponse(
            $request,
            $agent
        );
    }

    public function myWallet(
        Request $request,
        AgentWalletService $wallet
    ): JsonResponse {
        $agent = $this->currentAgent($request);

        if (!$agent) {
            return $this->sendError(
                'Agent profile was not found.',
                [],
                404
            );
        }

        $request->validate([
            'coffee_season_id' => [
                'nullable',
                'integer',
                'exists:coffee_seasons,id',
            ],
        ]);

        $seasonId = $request->filled('coffee_season_id')
            ? $request->integer('coffee_season_id')
            : null;

        return $this->sendResponse([
            'agent' => $this->agentData($agent),
            'summary' => $wallet->summary(
                $agent->id,
                $seasonId
            ),
        ], 'Wallet retrieved successfully.');
    }

    public function myTransactions(
        Request $request
    ): JsonResponse {
        $agent = $this->currentAgent($request);

        if (!$agent) {
            return $this->sendError(
                'Agent profile was not found.',
                [],
                404
            );
        }

        return $this->transactionResponse(
            $request,
            $agent
        );
    }

    private function transactionResponse(
        Request $request,
        Agent $agent
    ): JsonResponse {
        $request->validate([
            'coffee_season_id' => [
                'nullable',
                'integer',
                'exists:coffee_seasons,id',
            ],
            'direction' => [
                'nullable',
                'in:credit,debit',
            ],
            'type' => [
                'nullable',
                'string',
                'max:50',
            ],
            'date_from' => ['nullable', 'date'],
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

        $transactions = AgentWalletTransaction::query()
            ->with([
                'coffeeSeason:id,code,name,status',
                'creator:id,name',
            ])
            ->where('agent_id', $agent->id)
            ->when(
                $request->filled('coffee_season_id'),
                fn ($query) => $query->where(
                    'coffee_season_id',
                    $request->integer('coffee_season_id')
                )
            )
            ->when(
                $request->filled('direction'),
                fn ($query) => $query->where(
                    'direction',
                    $request->direction
                )
            )
            ->when(
                $request->filled('type'),
                fn ($query) => $query->where(
                    'type',
                    $request->type
                )
            )
            ->when(
                $request->filled('date_from'),
                fn ($query) => $query->whereDate(
                    'created_at',
                    '>=',
                    $request->date_from
                )
            )
            ->when(
                $request->filled('date_to'),
                fn ($query) => $query->whereDate(
                    'created_at',
                    '<=',
                    $request->date_to
                )
            )
            ->latest('id')
            ->paginate(
                min(max((int) $request->get('per_page', 20), 1), 100)
            );

        return $this->sendResponse([
            'items' => $transactions->getCollection()
                ->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'transaction_code' => $transaction->transaction_code,
                    'type' => $transaction->type,
                    'direction' => $transaction->direction,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'source_type' => $transaction->source_type,
                    'source_id' => $transaction->source_id,
                    'description' => $transaction->description,
                    'coffee_season' => $transaction->coffeeSeason
                        ? [
                            'id' => $transaction->coffeeSeason->id,
                            'code' => $transaction->coffeeSeason->code,
                            'name' => $transaction->coffeeSeason->name,
                        ]
                        : null,
                    'created_by' => $transaction->creator
                        ? [
                            'id' => $transaction->creator->id,
                            'name' => $transaction->creator->name,
                        ]
                        : null,
                    'created_at' => $transaction->created_at?->toISOString(),
                ])
                ->values(),
            'pagination' => $this->pagination($transactions),
        ], 'Wallet transactions retrieved successfully.');
    }

    private function currentAgent(Request $request): ?Agent
    {
        if ($request->user()?->role !== User::ROLE_AGENT) {
            return null;
        }

        return Agent::query()
            ->with('user')
            ->where('user_id', $request->user()->id)
            ->first();
    }

    private function canManageWallets(?User $user): bool
    {
        return $user &&
            in_array(
                $user->role,
                [
                    User::ROLE_ADMIN,
                    User::ROLE_ACCOUNTANT,
                ],
                true
            );
    }

    private function agentData(Agent $agent): array
    {
        return [
            'id' => $agent->id,
            'agent_code' => $agent->agent_code,
            'status' => $agent->status,
            'user' => $agent->user
                ? [
                    'id' => $agent->user->id,
                    'name' => $agent->user->name,
                    'phone' => $agent->user->phone,
                    'email' => $agent->user->email,
                ]
                : null,
        ];
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function money($amount): string
    {
        return number_format(
            (float) $amount,
            2,
            '.',
            ''
        );
    }
}
