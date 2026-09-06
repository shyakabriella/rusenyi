<?php

use App\Http\Controllers\API\CollectionPickup\CollectionPickupController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('collection-pickups')
    ->controller(
        CollectionPickupController::class
    )
    ->group(function () {
        Route::get(
            '/drivers',
            'drivers'
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
            '/{collectionPickup}',
            'show'
        );

        Route::get(
            '/{collectionPickup}/receipt',
            'receipt'
        );

        Route::patch(
            '/{collectionPickup}/accept',
            'accept'
        );

        Route::patch(
            '/{collectionPickup}/reject',
            'reject'
        );

        Route::patch(
            '/{collectionPickup}/confirm-weight',
            'confirmWeight'
        );

        Route::patch(
            '/{collectionPickup}/start-transport',
            'startTransport'
        );

        Route::patch(
            '/{collectionPickup}/arrive-factory',
            'arriveFactory'
        );

        Route::patch(
            '/{collectionPickup}/factory-weight',
            'recordFactoryWeight'
        );

        Route::patch(
            '/{collectionPickup}/acknowledge-factory-weight',
            'acknowledgeFactoryWeight'
        );

        Route::patch(
            '/{collectionPickup}/cancel',
            'cancel'
        );
    });
