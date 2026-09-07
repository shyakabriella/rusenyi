<?php

use App\Http\Controllers\API\PettyCash\PettyCashController;
use App\Http\Controllers\API\PettyCash\PettyCashExpenseController;
use App\Http\Controllers\API\PettyCash\PettyCashRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('petty-cash')
    ->group(function () {
        Route::get(
            '/dashboard',
            [PettyCashController::class, 'dashboard']
        );

        Route::get(
            '/transactions',
            [PettyCashController::class, 'transactions']
        );

        Route::get(
            '/requests',
            [PettyCashRequestController::class, 'index']
        );

        Route::post(
            '/requests',
            [PettyCashRequestController::class, 'store']
        );

        Route::get(
            '/requests/{pettyCashRequest}',
            [PettyCashRequestController::class, 'show']
        );

        Route::post(
            '/requests/{pettyCashRequest}/approve',
            [PettyCashRequestController::class, 'approve']
        );

        Route::post(
            '/requests/{pettyCashRequest}/reject',
            [PettyCashRequestController::class, 'reject']
        );

        Route::post(
            '/requests/{pettyCashRequest}/cancel',
            [PettyCashRequestController::class, 'cancel']
        );

        Route::get(
            '/expenses',
            [PettyCashExpenseController::class, 'index']
        );

        Route::post(
            '/expenses',
            [PettyCashExpenseController::class, 'store']
        );

        Route::post(
            '/expenses/{pettyCashExpense}/reverse',
            [PettyCashExpenseController::class, 'reverse']
        );
    });
