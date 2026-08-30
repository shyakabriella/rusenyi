<?php

namespace App\Http\Controllers\API\Admin\Location;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\API\Location\CollectionPointResource;
use App\Models\CollectionPoint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentCollectionPointController extends BaseController
{
    public function index(
        Request $request,
        User $agent
    ): JsonResponse {
        if (
            $agent->role !== User::ROLE_AGENT
        ) {
            return $this->sendError(
                'Invalid agent.',
                [
                    'agent' =>
                        'The selected user is not an Agent.',
                ],
                422
            );
        }

        return $this->sendResponse(
            $this->assignmentData(
                $request,
                $agent
            ),
            'Agent collection points retrieved successfully.'
        );
    }

    public function assign(
        Request $request,
        User $agent
    ): JsonResponse {
        if (
            $agent->role !== User::ROLE_AGENT
        ) {
            return $this->sendError(
                'Invalid agent.',
                [
                    'agent' =>
                        'The selected user is not an Agent.',
                ],
                422
            );
        }

        if (!$agent->is_active) {
            return $this->sendError(
                'Inactive agent.',
                [
                    'agent' =>
                        'Collection points cannot be assigned to an inactive Agent.',
                ],
                422
            );
        }

        $validated =
            $request->validate([
                'collection_point_ids' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'collection_point_ids.*' => [
                    'required',
                    'integer',
                    'distinct',
                    'exists:collection_points,id',
                ],
            ]);

        $ids = collect(
            $validated[
                'collection_point_ids'
            ]
        )
            ->map(
                fn ($id) => (int) $id
            )
            ->unique()
            ->values();

        $activePoints =
            CollectionPoint::query()
                ->whereIn('id', $ids)
                ->where(
                    'is_active',
                    true
                )
                ->pluck('id');

        if (
            $activePoints->count() !==
            $ids->count()
        ) {
            return $this->sendError(
                'Invalid collection point.',
                [
                    'collection_point_ids' =>
                        'All selected collection points must be active.',
                ],
                422
            );
        }

        DB::transaction(
            function () use (
                $agent,
                $ids
            ): void {
                foreach ($ids as $id) {
                    $existing =
                        $agent
                            ->collectionPoints()
                            ->where(
                                'collection_points.id',
                                $id
                            )
                            ->first();

                    if ($existing) {
                        $agent
                            ->collectionPoints()
                            ->updateExistingPivot(
                                $id,
                                [
                                    'is_active' =>
                                        true,

                                    'assigned_at' =>
                                        now(),

                                    'unassigned_at' =>
                                        null,
                                ]
                            );

                        continue;
                    }

                    $agent
                        ->collectionPoints()
                        ->attach(
                            $id,
                            [
                                'is_active' =>
                                    true,

                                'assigned_at' =>
                                    now(),

                                'unassigned_at' =>
                                    null,
                            ]
                        );
                }
            }
        );

        return $this->sendResponse(
            $this->assignmentData(
                $request,
                $agent
            ),
            'Collection points assigned successfully.'
        );
    }

    public function updateStatus(
        Request $request,
        User $agent,
        CollectionPoint $collectionPoint
    ): JsonResponse {
        if (
            $agent->role !== User::ROLE_AGENT
        ) {
            return $this->sendError(
                'Invalid agent.',
                [
                    'agent' =>
                        'The selected user is not an Agent.',
                ],
                422
            );
        }

        $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $assigned =
            $agent
                ->collectionPoints()
                ->where(
                    'collection_points.id',
                    $collectionPoint->id
                )
                ->exists();

        if (!$assigned) {
            return $this->sendError(
                'Assignment not found.',
                [
                    'assignment' =>
                        'This Agent is not assigned to the selected collection point.',
                ],
                404
            );
        }

        $active =
            $request->boolean(
                'is_active'
            );

        $agent
            ->collectionPoints()
            ->updateExistingPivot(
                $collectionPoint->id,
                [
                    'is_active' =>
                        $active,

                    'assigned_at' =>
                        $active
                            ? now()
                            : null,

                    'unassigned_at' =>
                        $active
                            ? null
                            : now(),
                ]
            );

        return $this->sendResponse(
            $this->assignmentData(
                $request,
                $agent
            ),
            $active
                ? 'Agent collection point assignment activated successfully.'
                : 'Agent collection point assignment deactivated successfully.'
        );
    }

    private function assignmentData(
        Request $request,
        User $agent
    ): array {
        $points =
            $agent
                ->collectionPoints()
                ->with([
                    'village.cell.sector.district.province',
                ])
                ->orderBy(
                    'collection_points.name'
                )
                ->get();

        return $points
            ->map(
                function (
                    CollectionPoint $point
                ) use ($request): array {
                    return [
                        'collection_point' =>
                            (
                                new CollectionPointResource(
                                    $point
                                )
                            )->resolve(
                                $request
                            ),

                        'assignment' => [
                            'is_active' =>
                                (bool) $point
                                    ->pivot
                                    ->is_active,

                            'assigned_at' =>
                                $point
                                    ->pivot
                                    ->assigned_at,

                            'unassigned_at' =>
                                $point
                                    ->pivot
                                    ->unassigned_at,
                        ],
                    ];
                }
            )
            ->values()
            ->all();
    }
}
