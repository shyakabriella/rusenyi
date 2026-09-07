<?php

use App\Http\Controllers\API\PettyCash\PettyCashRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('petty-cash/requests')
    ->group(function () {
        Route::get(
            '/summary',
            [PettyCashRequestController::class, 'summary']
        );

        Route::get(
            '/',
            [PettyCashRequestController::class, 'index']
        );

        Route::post(
            '/',
            [PettyCashRequestController::class, 'store']
        );

        Route::get(
            '/{pettyCashRequest}',
            [PettyCashRequestController::class, 'show']
        )->whereNumber(
            'pettyCashRequest'
        );

        Route::post(
            '/{pettyCashRequest}/approve',
            [PettyCashRequestController::class, 'approve']
        )->whereNumber(
            'pettyCashRequest'
        );

        Route::post(
            '/{pettyCashRequest}/reject',
            [PettyCashRequestController::class, 'reject']
        )->whereNumber(
            'pettyCashRequest'
        );

        Route::post(
            '/{pettyCashRequest}/cancel',
            [PettyCashRequestController::class, 'cancel']
        )->whereNumber(
            'pettyCashRequest'
        );
    });
