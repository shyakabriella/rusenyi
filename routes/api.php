<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\Admin\UserController;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [
    RegisterController::class,
    'login',
]);

Route::post('/forgot-password', [
    RegisterController::class,
    'forgotPassword',
]);

Route::post('/reset-password', [
    RegisterController::class,
    'resetPassword',
]);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Current Logged-In User
    |--------------------------------------------------------------------------
    |
    | /me identifies the exact logged-in user.
    | Multiple users can have the same role.
    |
    */

    Route::get('/me', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'User profile retrieved successfully.',
            'data' => $request->user(),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [
        RegisterController::class,
        'logout',
    ]);

    Route::post('/change-password', [
        RegisterController::class,
        'changePassword',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Routes Available After Temporary Password Change
    |--------------------------------------------------------------------------
    */

    Route::middleware('password.changed')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            Route::get('/roles', [
                RoleController::class,
                'index',
            ]);

            Route::get('/roles/active', [
                RoleController::class,
                'active',
            ]);

            Route::get('/roles/{id}', [
                RoleController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Admin Routes
            |--------------------------------------------------------------------------
            */

            Route::middleware('role:admin')
                ->prefix('admin')
                ->group(function () {

                    /*
                    |--------------------------------------------------------------------------
                    | User Management
                    |--------------------------------------------------------------------------
                    */

                    // List users
                    Route::get('/users', [
                        UserController::class,
                        'index',
                    ]);

                    // Create user
                    Route::post('/users', [
                        UserController::class,
                        'store',
                    ]);

                    // View one user
                    Route::get('/users/{user}', [
                        UserController::class,
                        'show',
                    ]);

                    // Update user
                    Route::put('/users/{user}', [
                        UserController::class,
                        'update',
                    ]);

                    // Activate / deactivate / suspend
                    Route::patch('/users/{user}/status', [
                        UserController::class,
                        'updateStatus',
                    ]);

                    // Generate a new temporary password
                    Route::post('/users/{user}/reset-credentials', [
                        UserController::class,
                        'resetCredentials',
                    ]);

                    // Resend credentials
                    Route::post('/users/{user}/resend-credentials', [
                        UserController::class,
                        'resendCredentials',
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Role Management
                    |--------------------------------------------------------------------------
                    */

                    Route::put('/roles/{id}', [
                        RoleController::class,
                        'update',
                    ]);
                });
        });
});


/*
|--------------------------------------------------------------------------
| GIHOMBO_LOCATION_MANAGEMENT_V1
|--------------------------------------------------------------------------
|
| Authenticated users may read active Rwanda location hierarchy.
| Only Admin may create/update/activate/deactivate locations and
| manage Agent ↔ Collection Point assignments.
|
*/

\Illuminate\Support\Facades\Route::prefix('locations')
    ->middleware([
        'auth:sanctum',
        'active.user',
        'password.changed',
    ])
    ->group(function (): void {

        \Illuminate\Support\Facades\Route::get(
            'provinces',
            [
                \App\Http\Controllers\API\Location\LocationLookupController::class,
                'provinces',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'districts',
            [
                \App\Http\Controllers\API\Location\LocationLookupController::class,
                'districts',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'sectors',
            [
                \App\Http\Controllers\API\Location\LocationLookupController::class,
                'sectors',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'cells',
            [
                \App\Http\Controllers\API\Location\LocationLookupController::class,
                'cells',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'villages',
            [
                \App\Http\Controllers\API\Location\LocationLookupController::class,
                'villages',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'collection-points',
            [
                \App\Http\Controllers\API\Location\LocationLookupController::class,
                'collectionPoints',
            ]
        );
    });


\Illuminate\Support\Facades\Route::prefix(
    'admin/locations'
)
    ->middleware([
        'auth:sanctum',
        'active.user',
        'password.changed',
        'role:admin',
    ])
    ->group(function (): void {

        /*
        |--------------------------------------------------------------
        | Provinces
        |--------------------------------------------------------------
        */

        \Illuminate\Support\Facades\Route::get(
            'provinces',
            [
                \App\Http\Controllers\API\Admin\Location\ProvinceController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            'provinces',
            [
                \App\Http\Controllers\API\Admin\Location\ProvinceController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'provinces/{province}',
            [
                \App\Http\Controllers\API\Admin\Location\ProvinceController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            'provinces/{province}',
            [
                \App\Http\Controllers\API\Admin\Location\ProvinceController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            'provinces/{province}/status',
            [
                \App\Http\Controllers\API\Admin\Location\ProvinceController::class,
                'updateStatus',
            ]
        );

        /*
        |--------------------------------------------------------------
        | Districts
        |--------------------------------------------------------------
        */

        \Illuminate\Support\Facades\Route::get(
            'districts',
            [
                \App\Http\Controllers\API\Admin\Location\DistrictController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            'districts',
            [
                \App\Http\Controllers\API\Admin\Location\DistrictController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'districts/{district}',
            [
                \App\Http\Controllers\API\Admin\Location\DistrictController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            'districts/{district}',
            [
                \App\Http\Controllers\API\Admin\Location\DistrictController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            'districts/{district}/status',
            [
                \App\Http\Controllers\API\Admin\Location\DistrictController::class,
                'updateStatus',
            ]
        );

        /*
        |--------------------------------------------------------------
        | Sectors
        |--------------------------------------------------------------
        */

        \Illuminate\Support\Facades\Route::get(
            'sectors',
            [
                \App\Http\Controllers\API\Admin\Location\SectorController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            'sectors',
            [
                \App\Http\Controllers\API\Admin\Location\SectorController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'sectors/{sector}',
            [
                \App\Http\Controllers\API\Admin\Location\SectorController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            'sectors/{sector}',
            [
                \App\Http\Controllers\API\Admin\Location\SectorController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            'sectors/{sector}/status',
            [
                \App\Http\Controllers\API\Admin\Location\SectorController::class,
                'updateStatus',
            ]
        );

        /*
        |--------------------------------------------------------------
        | Cells
        |--------------------------------------------------------------
        */

        \Illuminate\Support\Facades\Route::get(
            'cells',
            [
                \App\Http\Controllers\API\Admin\Location\CellController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            'cells',
            [
                \App\Http\Controllers\API\Admin\Location\CellController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'cells/{cell}',
            [
                \App\Http\Controllers\API\Admin\Location\CellController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            'cells/{cell}',
            [
                \App\Http\Controllers\API\Admin\Location\CellController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            'cells/{cell}/status',
            [
                \App\Http\Controllers\API\Admin\Location\CellController::class,
                'updateStatus',
            ]
        );

        /*
        |--------------------------------------------------------------
        | Villages
        |--------------------------------------------------------------
        */

        \Illuminate\Support\Facades\Route::get(
            'villages',
            [
                \App\Http\Controllers\API\Admin\Location\VillageController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            'villages',
            [
                \App\Http\Controllers\API\Admin\Location\VillageController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'villages/{village}',
            [
                \App\Http\Controllers\API\Admin\Location\VillageController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            'villages/{village}',
            [
                \App\Http\Controllers\API\Admin\Location\VillageController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            'villages/{village}/status',
            [
                \App\Http\Controllers\API\Admin\Location\VillageController::class,
                'updateStatus',
            ]
        );

        /*
        |--------------------------------------------------------------
        | Collection Points
        |--------------------------------------------------------------
        */

        \Illuminate\Support\Facades\Route::get(
            'collection-points',
            [
                \App\Http\Controllers\API\Admin\Location\CollectionPointController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            'collection-points',
            [
                \App\Http\Controllers\API\Admin\Location\CollectionPointController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            'collection-points/{collectionPoint}',
            [
                \App\Http\Controllers\API\Admin\Location\CollectionPointController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            'collection-points/{collectionPoint}',
            [
                \App\Http\Controllers\API\Admin\Location\CollectionPointController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            'collection-points/{collectionPoint}/status',
            [
                \App\Http\Controllers\API\Admin\Location\CollectionPointController::class,
                'updateStatus',
            ]
        );

        /*
        |--------------------------------------------------------------
        | Agent ↔ Collection Points
        |--------------------------------------------------------------
        */

        \Illuminate\Support\Facades\Route::get(
            'agents/{agent}/collection-points',
            [
                \App\Http\Controllers\API\Admin\Location\AgentCollectionPointController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            'agents/{agent}/collection-points',
            [
                \App\Http\Controllers\API\Admin\Location\AgentCollectionPointController::class,
                'assign',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            'agents/{agent}/collection-points/{collectionPoint}/status',
            [
                \App\Http\Controllers\API\Admin\Location\AgentCollectionPointController::class,
                'updateStatus',
            ]
        );
    });


/*
|--------------------------------------------------------------------------
| GIHOMBO_COFFEE_SEASON_MANAGEMENT_V1
|--------------------------------------------------------------------------
*/

\Illuminate\Support\Facades\Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])->group(function (): void {

    \Illuminate\Support\Facades\Route::get(
        'coffee-seasons/active',
        \App\Http\Controllers\API\CoffeeSeason\ActiveCoffeeSeasonController::class
    );

});


\Illuminate\Support\Facades\Route::prefix(
    'admin/coffee-seasons'
)
    ->middleware([
        'auth:sanctum',
        'active.user',
        'password.changed',
        'role:admin',
    ])
    ->group(function (): void {

        \Illuminate\Support\Facades\Route::get(
            '/',
            [
                \App\Http\Controllers\API\Admin\CoffeeSeason\CoffeeSeasonController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            '/',
            [
                \App\Http\Controllers\API\Admin\CoffeeSeason\CoffeeSeasonController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            '{coffeeSeason}',
            [
                \App\Http\Controllers\API\Admin\CoffeeSeason\CoffeeSeasonController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            '{coffeeSeason}',
            [
                \App\Http\Controllers\API\Admin\CoffeeSeason\CoffeeSeasonController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{coffeeSeason}/activate',
            [
                \App\Http\Controllers\API\Admin\CoffeeSeason\CoffeeSeasonController::class,
                'activate',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{coffeeSeason}/close',
            [
                \App\Http\Controllers\API\Admin\CoffeeSeason\CoffeeSeasonController::class,
                'close',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{coffeeSeason}/reopen',
            [
                \App\Http\Controllers\API\Admin\CoffeeSeason\CoffeeSeasonController::class,
                'reopen',
            ]
        );
    });


/*
|--------------------------------------------------------------------------
| GIHOMBO_COFFEE_PRICE_MANAGEMENT_V1
|--------------------------------------------------------------------------
|
| Admin manages price history.
| Authenticated operational users may read current active prices.
|
*/

\Illuminate\Support\Facades\Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])->group(function (): void {

    \Illuminate\Support\Facades\Route::get(
        'coffee-prices/active',
        \App\Http\Controllers\API\CoffeePrice\ActiveCoffeePriceController::class
    );
});


\Illuminate\Support\Facades\Route::prefix(
    'admin/coffee-prices'
)
    ->middleware([
        'auth:sanctum',
        'active.user',
        'password.changed',
        'role:admin',
    ])
    ->group(function (): void {

        \Illuminate\Support\Facades\Route::get(
            '/',
            [
                \App\Http\Controllers\API\Admin\CoffeePrice\CoffeePriceController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            '/',
            [
                \App\Http\Controllers\API\Admin\CoffeePrice\CoffeePriceController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            '{coffeePrice}',
            [
                \App\Http\Controllers\API\Admin\CoffeePrice\CoffeePriceController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            '{coffeePrice}',
            [
                \App\Http\Controllers\API\Admin\CoffeePrice\CoffeePriceController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{coffeePrice}/activate',
            [
                \App\Http\Controllers\API\Admin\CoffeePrice\CoffeePriceController::class,
                'activate',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{coffeePrice}/deactivate',
            [
                \App\Http\Controllers\API\Admin\CoffeePrice\CoffeePriceController::class,
                'deactivate',
            ]
        );
    });
