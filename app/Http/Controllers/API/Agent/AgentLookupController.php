<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\API\Agent\AgentResource;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentLookupController extends BaseController
{
    public function __invoke(
        Request $request
    ): JsonResponse {
        $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'village_id' => [
                'nullable',
                'integer',
                'exists:villages,id',
            ],
        ]);

        $agents =
            Agent::query()
                ->with([
                    'user',
                    'homeVillage.cell.sector.district.province',
                ])

                ->active()

                ->whereHas(
                    'user',
                    function ($query) {
                        $query
                            ->where(
                                'role',
                                User::ROLE_AGENT
                            )
                            ->where(
                                'status',
                                'active'
                            );
                    }
                )

                ->when(
                    $request->filled(
                        'search'
                    ),
                    function (
                        $query
                    ) use ($request) {
                        $search =
                            trim(
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
                                                    'phone',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'email',
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

                ->orderBy(
                    'agent_code'
                )

                ->limit(50)
                ->get();

        return $this->sendResponse(
            [
                'items' =>
                    AgentResource::collection(
                        $agents
                    )->resolve(
                        $request
                    ),
            ],
            'Active agents retrieved successfully.'
        );
    }
}
