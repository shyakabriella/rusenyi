<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Agent\StoreAgentRequest;
use App\Http\Requests\API\Agent\UpdateAgentRequest;
use App\Http\Resources\API\Agent\AgentResource;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureAuthorized($request);

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            100
        );

        $agents = Agent::query()
            ->with([
                'user',
                'assignedLocation',
            ])
            ->search($request->string('search')->toString())
            ->when(
                $request->filled('assigned_location_id'),
                fn ($query) =>
                    $query->where(
                        'assigned_location_id',
                        $request->integer('assigned_location_id')
                    )
            )
            ->orderBy('agent_code')
            ->paginate($perPage)
            ->withQueryString();

        return AgentResource::collection($agents)
            ->additional([
                'success' => true,
                'message' => 'Agents retrieved successfully.',
            ]);
    }

    public function store(StoreAgentRequest $request)
    {
        $agent = Agent::create(
            $request->validated()
        );

        $agent->load([
            'user',
            'assignedLocation',
        ]);

        return (new AgentResource($agent))
            ->additional([
                'success' => true,
                'message' => 'Agent profile created successfully.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        Agent $agent
    ): AgentResource {
        $this->ensureAuthorized($request);

        $agent->load([
            'user',
            'assignedLocation',
        ]);

        return (new AgentResource($agent))
            ->additional([
                'success' => true,
                'message' => 'Agent retrieved successfully.',
            ]);
    }

    public function update(
        UpdateAgentRequest $request,
        Agent $agent
    ): AgentResource {
        $agent->update(
            $request->validated()
        );

        $agent->load([
            'user',
            'assignedLocation',
        ]);

        return (new AgentResource($agent))
            ->additional([
                'success' => true,
                'message' => 'Agent updated successfully.',
            ]);
    }

    private function ensureAuthorized(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole([
                'admin',
                'accountant',
            ]),
            403,
            'You are not authorized to access agent management.'
        );
    }
}
