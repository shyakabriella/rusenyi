<?php

namespace App\Http\Controllers\API\WorkerAttendance;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkerAttendanceController extends BaseController
{
    public function index(
        Request $request
    ): JsonResponse {
        if (!$this->canRead($request->user())) {
            return $this->sendError(
                'You are not allowed to view Worker attendance.',
                [],
                403
            );
        }

        $validated = $request->validate([
            'date' => [
                'required',
                'date',
            ],
        ]);

        $date = $validated['date'];

        $attendance =
            WorkerAttendance::query()
                ->whereDate(
                    'attendance_date',
                    $date
                )
                ->get()
                ->keyBy('worker_id');

        $workers =
            Worker::query()
                ->where(
                    'status',
                    Worker::STATUS_ACTIVE
                )
                ->orderBy('name')
                ->get()
                ->map(function (Worker $worker) use ($attendance) {
                    $record =
                        $attendance->get(
                            $worker->id
                        );

                    return [
                        'worker_id' =>
                            $worker->id,

                        'worker_code' =>
                            $worker->worker_code,

                        'name' =>
                            $worker->name,

                        'phone' =>
                            $worker->phone,

                        'email' =>
                            $worker->email,

                        'national_id' =>
                            $worker->national_id,

                        'attendance_id' =>
                            $record?->id,

                        'status' =>
                            $record?->status,

                        'notes' =>
                            $record?->notes,

                        'recorded_by' =>
                            $record?->recorded_by,
                    ];
                })
                ->values();

        return $this->sendResponse([
            'date' =>
                $date,

            'locked' =>
                $attendance->isNotEmpty(),

            'workers' =>
                $workers,

            'summary' => [
                'workers' =>
                    $workers->count(),

                'recorded' =>
                    $workers
                        ->whereNotNull('status')
                        ->count(),

                'present' =>
                    $workers
                        ->where(
                            'status',
                            WorkerAttendance::STATUS_PRESENT
                        )
                        ->count(),

                'absent' =>
                    $workers
                        ->where(
                            'status',
                            WorkerAttendance::STATUS_ABSENT
                        )
                        ->count(),

                'late' =>
                    $workers
                        ->where(
                            'status',
                            WorkerAttendance::STATUS_LATE
                        )
                        ->count(),

                'excused' =>
                    $workers
                        ->where(
                            'status',
                            WorkerAttendance::STATUS_EXCUSED
                        )
                        ->count(),
            ],
        ], 'Worker attendance retrieved successfully.');
    }

    public function store(
        Request $request
    ): JsonResponse {
        if (!$this->canRecord($request->user())) {
            return $this->sendError(
                'You are not allowed to record Worker attendance.',
                [],
                403
            );
        }

        $validated = $request->validate([
            'date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'attendances' => [
                'required',
                'array',
                'min:1',
            ],

            'attendances.*.worker_id' => [
                'required',
                'integer',
                'distinct',
                'exists:workers,id',
            ],

            'attendances.*.status' => [
                'required',
                Rule::in(
                    WorkerAttendance::STATUSES
                ),
            ],

            'attendances.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        if (
            $request->user()->role === 'store' &&
            $validated['date'] !== now()->toDateString()
        ) {
            return $this->sendError(
                'Store Officer can only record attendance for today.',
                [],
                422
            );
        }

        if (
            $request->user()->role === 'store' &&
            WorkerAttendance::query()
                ->whereDate(
                    'attendance_date',
                    $validated['date']
                )
                ->exists()
        ) {
            return $this->sendError(
                'Attendance for today has already been saved. The next attendance can be recorded tomorrow.',
                [],
                422
            );
        }

        DB::transaction(
            function () use (
                $validated,
                $request
            ) {
                foreach (
                    $validated['attendances']
                    as $item
                ) {
                    WorkerAttendance::updateOrCreate(
                        [
                            'worker_id' =>
                                $item['worker_id'],

                            'attendance_date' =>
                                $validated['date'],
                        ],
                        [
                            'status' =>
                                $item['status'],

                            'notes' =>
                                $item['notes']
                                ?? null,

                            'recorded_by' =>
                                $request->user()->id,
                        ]
                    );
                }
            }
        );

        return $this->sendResponse(
            [
                'date' =>
                    $validated['date'],

                'saved' =>
                    count(
                        $validated['attendances']
                    ),
            ],
            'Daily Worker attendance saved successfully.'
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

    private function canRecord(
        ?User $user
    ): bool {
        return $user &&
            in_array(
                $user->role,
                [
                    'admin',
                    'store',
                ],
                true
            );
    }
}
