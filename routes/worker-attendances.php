<?php

use App\Http\Controllers\API\WorkerAttendance\WorkerAttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('worker-attendances')
    ->controller(
        WorkerAttendanceController::class
    )
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
    });
