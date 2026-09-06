<?php

use App\Http\Controllers\API\Worker\WorkerController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('workers')
    ->controller(
        WorkerController::class
    )
    ->group(function () {
        Route::get(
            '/lookup',
            'lookup'
        );

        Route::get(
            '/',
            'index'
        );

        Route::post(
            '/',
            'store'
        );

        Route::get(
            '/{worker}',
            'show'
        );

        Route::put(
            '/{worker}',
            'update'
        );

        Route::patch(
            '/{worker}/status',
            'changeStatus'
        );
    });
