<?php

namespace App\Http\Controllers\API\Worker;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Worker\StoreWorkerRequest;
use App\Http\Requests\API\Worker\UpdateWorkerRequest;
use App\Http\Requests\API\Worker\UpdateWorkerStatusRequest;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerController extends BaseController
{
    public function index(
        Request $request
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Workers.',
                [],
                403
            );
        }

        $query =
            Worker::query()
                ->with([
                    'user:id,name,email,phone,role,status,is_active',
                ]);

        if ($request->filled('search')) {
            $search =
                trim(
                    (string) $request->search
                );

            $query->where(
                function (Builder $query) use ($search) {
                    $query
                        ->where(
                            'worker_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
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
                        )
                        ->orWhere(
                            'national_id',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        $items =
            $query
                ->orderBy('name')
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
            'workers' =>
                collect(
                    $items->items()
                )
                    ->map(
                        fn (Worker $worker) =>
                            $this->data($worker)
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
        ], 'Workers retrieved successfully.');
    }

    public function lookup(
        Request $request
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to access Worker lookup.',
                [],
                403
            );
        }

        $workers =
            Worker::query()
                ->where(
                    'status',
                    Worker::STATUS_ACTIVE
                )
                ->orderBy('name')
                ->get();

        return $this->sendResponse([
            'items' =>
                $workers
                    ->map(
                        fn (Worker $worker) =>
                            $this->data($worker)
                    )
                    ->values(),
        ], 'Worker lookup retrieved successfully.');
    }

    public function store(
        StoreWorkerRequest $request
    ): JsonResponse {
        $worker =
            Worker::create([
                'worker_code' =>
                    'TMP-' .
                    strtoupper(
                        substr(
                            str_replace(
                                '-',
                                '',
                                (string) \Illuminate\Support\Str::uuid()
                            ),
                            0,
                            20
                        )
                    ),

                'name' =>
                    trim(
                        $request->string(
                            'name'
                        )->toString()
                    ),

                'phone' =>
                    $request->phone,

                'email' =>
                    $request->email,

                'national_id' =>
                    $request->national_id,

                'worker_type' =>
                    Worker::TYPE_CASUAL,

                'status' =>
                    Worker::STATUS_ACTIVE,

                'notes' =>
                    $request->notes,

                'created_by' =>
                    $request->user()->id,
            ]);

        $worker->forceFill([
            'worker_code' =>
                sprintf(
                    'WRK-%06d',
                    $worker->id
                ),
        ])->saveQuietly();

        return $this->sendResponse(
            $this->data(
                $worker->fresh()
            ),
            'Worker created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        Worker $worker
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view this Worker.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $this->data(
                $worker->load('user')
            ),
            'Worker retrieved successfully.'
        );
    }

    public function update(
        UpdateWorkerRequest $request,
        Worker $worker
    ): JsonResponse {
        $data =
            $request->validated();

        $data['updated_by'] =
            $request->user()->id;

        $worker->update($data);

        return $this->sendResponse(
            $this->data(
                $worker
                    ->fresh()
                    ->load('user')
            ),
            'Worker updated successfully.'
        );
    }

    public function changeStatus(
        UpdateWorkerStatusRequest $request,
        Worker $worker
    ): JsonResponse {
        $worker->update([
            'status' =>
                $request->status,

            'status_changed_by' =>
                $request->user()->id,

            'status_changed_at' =>
                now(),

            'updated_by' =>
                $request->user()->id,
        ]);

        return $this->sendResponse(
            $this->data(
                $worker->fresh()
            ),
            'Worker status updated successfully.'
        );
    }

    private function canRead(
        ?User $user
    ): bool {
        return $user &&
            in_array(
                $user->role,
                [
                    'admin',
                    'accountant',
                    'store',
                ],
                true
            );
    }

    private function data(
        Worker $worker
    ): array {
        return [
            'id' =>
                $worker->id,

            'worker_code' =>
                $worker->worker_code,

            'user_id' =>
                $worker->user_id,

            'name' =>
                $worker->name,

            'phone' =>
                $worker->phone,

            'email' =>
                $worker->email,

            'national_id' =>
                $worker->national_id,

            'worker_type' =>
                $worker->worker_type,

            'status' =>
                $worker->status,

            'has_account' =>
                $worker->user_id !== null,

            'notes' =>
                $worker->notes,

            'created_at' =>
                $worker->created_at
                    ?->toISOString(),

            'updated_at' =>
                $worker->updated_at
                    ?->toISOString(),
        ];
    }
}
