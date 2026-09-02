<?php

namespace App\Http\Controllers\API\Admin\Agent;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Admin\Agent\StoreAgentRequest;
use App\Http\Requests\API\Admin\Agent\UpdateAgentRequest;
use App\Http\Resources\API\Agent\AgentResource;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends BaseController
{
    private array $relations = [
        'user',
        'homeVillage.cell.sector.district.province',
        'creator',
        'updater',
        'deactivator',
        'reactivator',
    ];

    public function index(
        Request $request
    ): JsonResponse {
        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'status' => [
                'nullable',
                'in:active,inactive',
            ],

            'province_id' => [
                'nullable',
                'integer',
                'exists:provinces,id',
            ],

            'district_id' => [
                'nullable',
                'integer',
                'exists:districts,id',
            ],

            'sector_id' => [
                'nullable',
                'integer',
                'exists:sectors,id',
            ],

            'cell_id' => [
                'nullable',
                'integer',
                'exists:cells,id',
            ],

            'village_id' => [
                'nullable',
                'integer',
                'exists:villages,id',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query =
            Agent::query()
                ->with(
                    $this->relations
                )

                ->when(
                    $request->filled(
                        'search'
                    ),
                    function (
                        $query
                    ) use ($request) {
                        $search = trim(
                            (string)
                            $request->search
                        );

                        $query->where(
                            function (
                                $query
                            ) use ($search) {
                                $query
                                    ->where(
                                        'agent_code',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhereHas(
                                        'user',
                                        function (
                                            $userQuery
                                        ) use (
                                            $search
                                        ) {
                                            $userQuery
                                                ->where(
                                                    'name',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'email',
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
                )

                ->when(
                    $request->filled(
                        'status'
                    ),
                    fn ($query) =>
                        $query->where(
                            'status',
                            $request->status
                        )
                )

                ->when(
                    $request->filled(
                        'village_id'
                    ),
                    fn ($query) =>
                        $query->where(
                            'home_village_id',
                            $request->integer(
                                'village_id'
                            )
                        )
                )

                ->when(
                    $request->filled(
                        'cell_id'
                    ),
                    fn ($query) =>
                        $query->whereHas(
                            'homeVillage',
                            fn ($village) =>
                                $village->where(
                                    'cell_id',
                                    $request->integer(
                                        'cell_id'
                                    )
                                )
                        )
                )

                ->when(
                    $request->filled(
                        'sector_id'
                    ),
                    fn ($query) =>
                        $query->whereHas(
                            'homeVillage.cell',
                            fn ($cell) =>
                                $cell->where(
                                    'sector_id',
                                    $request->integer(
                                        'sector_id'
                                    )
                                )
                        )
                )

                ->when(
                    $request->filled(
                        'district_id'
                    ),
                    fn ($query) =>
                        $query->whereHas(
                            'homeVillage.cell.sector',
                            fn ($sector) =>
                                $sector->where(
                                    'district_id',
                                    $request->integer(
                                        'district_id'
                                    )
                                )
                        )
                )

                ->when(
                    $request->filled(
                        'province_id'
                    ),
                    fn ($query) =>
                        $query->whereHas(
                            'homeVillage.cell.sector.district',
                            fn ($district) =>
                                $district->where(
                                    'province_id',
                                    $request->integer(
                                        'province_id'
                                    )
                                )
                        )
                )

                ->orderBy('agent_code');

        $agents =
            $query->paginate(
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
                    AgentResource::collection(
                        $agents
                            ->getCollection()
                    )->resolve(
                        $request
                    ),

                'pagination' => [
                    'current_page' =>
                        $agents
                            ->currentPage(),

                    'last_page' =>
                        $agents
                            ->lastPage(),

                    'per_page' =>
                        $agents
                            ->perPage(),

                    'total' =>
                        $agents
                            ->total(),

                    'from' =>
                        $agents
                            ->firstItem(),

                    'to' =>
                        $agents
                            ->lastItem(),
                ],
            ],
            'Agents retrieved successfully.'
        );
    }

    public function store(
        StoreAgentRequest $request
    ): JsonResponse {
        $agent =
            DB::transaction(
                function () use (
                    $request
                ) {
                    $data =
                        $request
                            ->validated();

                    $data[
                        'agent_code'
                    ] =
                        $this
                            ->generateAgentCode();

                    $data['status'] =
                        Agent::STATUS_ACTIVE;

                    $data['created_by'] =
                        $request
                            ->user()
                            ->id;

                    return Agent::create(
                        $data
                    );
                }
            );

        $agent->load(
            $this->relations
        );

        return $this->sendResponse(
            new AgentResource(
                $agent
            ),
            'Agent profile created successfully.',
            201
        );
    }

    public function show(
        Agent $agent
    ): JsonResponse {
        $agent->load(
            $this->relations
        );

        return $this->sendResponse(
            new AgentResource(
                $agent
            ),
            'Agent retrieved successfully.'
        );
    }

    public function update(
        UpdateAgentRequest $request,
        Agent $agent
    ): JsonResponse {
        $data =
            $request
                ->validated();

        $data['updated_by'] =
            $request
                ->user()
                ->id;

        $agent->update($data);

        $agent =
            $agent
                ->fresh()
                ->load(
                    $this->relations
                );

        return $this->sendResponse(
            new AgentResource(
                $agent
            ),
            'Agent updated successfully.'
        );
    }

    public function deactivate(
        Request $request,
        Agent $agent
    ): JsonResponse {
        if (
            $agent->status ===
            Agent::STATUS_INACTIVE
        ) {
            return $this->sendResponse(
                new AgentResource(
                    $agent->load(
                        $this->relations
                    )
                ),
                'Agent is already inactive.'
            );
        }

        $agent->update([
            'status' =>
                Agent::STATUS_INACTIVE,

            'deactivated_by' =>
                $request
                    ->user()
                    ->id,

            'deactivated_at' =>
                now(),

            'reactivated_by' =>
                null,

            'reactivated_at' =>
                null,

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return $this->sendResponse(
            new AgentResource(
                $agent
                    ->fresh()
                    ->load(
                        $this->relations
                    )
            ),
            'Agent deactivated successfully.'
        );
    }

    public function reactivate(
        Request $request,
        Agent $agent
    ): JsonResponse {
        if (
            $agent->status ===
            Agent::STATUS_ACTIVE
        ) {
            return $this->sendResponse(
                new AgentResource(
                    $agent->load(
                        $this->relations
                    )
                ),
                'Agent is already active.'
            );
        }

        if (
            $agent->user &&
            isset(
                $agent->user->status
            ) &&
            $agent->user->status !==
                'active'
        ) {
            return $this->sendError(
                'Agent cannot be reactivated because the linked user account is not active.',
                [],
                422
            );
        }

        $agent->update([
            'status' =>
                Agent::STATUS_ACTIVE,

            'reactivated_by' =>
                $request
                    ->user()
                    ->id,

            'reactivated_at' =>
                now(),

            'deactivated_by' =>
                null,

            'deactivated_at' =>
                null,

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        return $this->sendResponse(
            new AgentResource(
                $agent
                    ->fresh()
                    ->load(
                        $this->relations
                    )
            ),
            'Agent reactivated successfully.'
        );
    }

    private function generateAgentCode(): string
    {
        $prefix = 'AGT-';

        $lastCode =
            Agent::query()
                ->where(
                    'agent_code',
                    'like',
                    $prefix . '%'
                )
                ->orderByDesc(
                    'agent_code'
                )
                ->value(
                    'agent_code'
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
                Agent::query()
                    ->where(
                        'agent_code',
                        $code
                    )
                    ->exists();

            $nextNumber++;
        } while ($exists);

        return $code;
    }
}
