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


/*
|--------------------------------------------------------------------------
| GIHOMBO_FARMER_MANAGEMENT_V1
|--------------------------------------------------------------------------
|
| Admin manages farmer profiles.
| Authenticated active users may search active farmers for operations.
|
*/

\Illuminate\Support\Facades\Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])->group(function (): void {

    \Illuminate\Support\Facades\Route::get(
        'farmers/lookup',
        \App\Http\Controllers\API\Farmer\FarmerLookupController::class
    );
});


\Illuminate\Support\Facades\Route::prefix(
    'admin/farmers'
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
                \App\Http\Controllers\API\Admin\Farmer\FarmerController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            '/',
            [
                \App\Http\Controllers\API\Admin\Farmer\FarmerController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            '{farmer}',
            [
                \App\Http\Controllers\API\Admin\Farmer\FarmerController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            '{farmer}',
            [
                \App\Http\Controllers\API\Admin\Farmer\FarmerController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{farmer}/deactivate',
            [
                \App\Http\Controllers\API\Admin\Farmer\FarmerController::class,
                'deactivate',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{farmer}/reactivate',
            [
                \App\Http\Controllers\API\Admin\Farmer\FarmerController::class,
                'reactivate',
            ]
        );
    });


/*
|--------------------------------------------------------------------------
| GIHOMBO_AGENT_MANAGEMENT_V1
|--------------------------------------------------------------------------
|
| Agent operational profiles.
| Collection-point assignments remain managed by the existing
| Location Management module through agent_collection_points.
|
*/

\Illuminate\Support\Facades\Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])->group(function (): void {

    \Illuminate\Support\Facades\Route::get(
        'agents/lookup',
        \App\Http\Controllers\API\Agent\AgentLookupController::class
    );
});


\Illuminate\Support\Facades\Route::prefix(
    'admin/agents'
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
                \App\Http\Controllers\API\Admin\Agent\AgentController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            '/',
            [
                \App\Http\Controllers\API\Admin\Agent\AgentController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            '{agent}',
            [
                \App\Http\Controllers\API\Admin\Agent\AgentController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            '{agent}',
            [
                \App\Http\Controllers\API\Admin\Agent\AgentController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{agent}/deactivate',
            [
                \App\Http\Controllers\API\Admin\Agent\AgentController::class,
                'deactivate',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{agent}/reactivate',
            [
                \App\Http\Controllers\API\Admin\Agent\AgentController::class,
                'reactivate',
            ]
        );
    });


/*
|--------------------------------------------------------------------------
| GIHOMBO_CASH_ALLOCATION_V1
|--------------------------------------------------------------------------
|
| Admin and Accountant manage money allocated to field agents.
| Approved allocations will feed the Agent Wallet module.
|
*/

\Illuminate\Support\Facades\Route::prefix(
    'finance/cash-allocations'
)
    ->middleware([
        'auth:sanctum',
        'active.user',
        'password.changed',
    ])
    ->group(function (): void {

        \Illuminate\Support\Facades\Route::get(
            'summary',
            [
                \App\Http\Controllers\API\Finance\CashAllocationController::class,
                'summary',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            '/',
            [
                \App\Http\Controllers\API\Finance\CashAllocationController::class,
                'index',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            '/',
            [
                \App\Http\Controllers\API\Finance\CashAllocationController::class,
                'store',
            ]
        );

        \Illuminate\Support\Facades\Route::get(
            '{cashAllocation}',
            [
                \App\Http\Controllers\API\Finance\CashAllocationController::class,
                'show',
            ]
        );

        \Illuminate\Support\Facades\Route::put(
            '{cashAllocation}',
            [
                \App\Http\Controllers\API\Finance\CashAllocationController::class,
                'update',
            ]
        );

        \Illuminate\Support\Facades\Route::post(
            '{cashAllocation}/proof',
            [
                \App\Http\Controllers\API\Finance\CashAllocationController::class,
                'uploadProof',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{cashAllocation}/approve',
            [
                \App\Http\Controllers\API\Finance\CashAllocationController::class,
                'approve',
            ]
        );

        \Illuminate\Support\Facades\Route::patch(
            '{cashAllocation}/cancel',
            [
                \App\Http\Controllers\API\Finance\CashAllocationController::class,
                'cancel',
            ]
        );
    });

/*
|--------------------------------------------------------------------------
| Agent Cash Wallet
|--------------------------------------------------------------------------
*/

\Illuminate\Support\Facades\Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])->group(function () {
    \Illuminate\Support\Facades\Route::prefix('finance/agent-wallets')
        ->group(function () {
            \Illuminate\Support\Facades\Route::get(
                'summary',
                [
                    \App\Http\Controllers\API\Finance\AgentWalletController::class,
                    'summary',
                ]
            );

            \Illuminate\Support\Facades\Route::get(
                '/',
                [
                    \App\Http\Controllers\API\Finance\AgentWalletController::class,
                    'index',
                ]
            );

            \Illuminate\Support\Facades\Route::get(
                '{agent}/transactions',
                [
                    \App\Http\Controllers\API\Finance\AgentWalletController::class,
                    'transactions',
                ]
            );

            \Illuminate\Support\Facades\Route::get(
                '{agent}',
                [
                    \App\Http\Controllers\API\Finance\AgentWalletController::class,
                    'show',
                ]
            );
        });

    \Illuminate\Support\Facades\Route::get(
        'agent/wallet',
        [
            \App\Http\Controllers\API\Finance\AgentWalletController::class,
            'myWallet',
        ]
    );

    \Illuminate\Support\Facades\Route::get(
        'agent/wallet/transactions',
        [
            \App\Http\Controllers\API\Finance\AgentWalletController::class,
            'myTransactions',
        ]
    );
});



/*
|--------------------------------------------------------------------------
| Coffee Purchases
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('coffee-purchases')
    ->controller(
        \App\Http\Controllers\API\CoffeePurchase\CoffeePurchaseController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');
        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get('/{coffeePurchase}', 'show');
        Route::put('/{coffeePurchase}', 'update');

        Route::patch(
            '/{coffeePurchase}/approve',
            'approve'
        );

        Route::patch(
            '/{coffeePurchase}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Direct Farmer Deliveries
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('direct-farmer-deliveries')
    ->controller(
        \App\Http\Controllers\API\DirectFarmerDelivery\DirectFarmerDeliveryController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');
        Route::get('/balance-officers', 'balanceOfficers');

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get('/{directFarmerDelivery}', 'show');
        Route::put('/{directFarmerDelivery}', 'update');

        Route::patch(
            '/{directFarmerDelivery}/confirm',
            'confirm'
        );

        Route::post(
            '/{directFarmerDelivery}/payment-proof',
            'uploadProof'
        );

        Route::patch(
            '/{directFarmerDelivery}/pay',
            'pay'
        );

        Route::patch(
            '/{directFarmerDelivery}/cancel',
            'cancel'
        );
    });


Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('agent-collections')
    ->controller(
        \App\Http\Controllers\API\AgentCollection\AgentCollectionController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');
        Route::get('/lookup', 'lookup');
        Route::get(
            '/eligible-purchases',
            'eligiblePurchases'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get('/{agentCollection}', 'show');
        Route::put('/{agentCollection}', 'update');

        Route::post(
            '/{agentCollection}/purchases',
            'addPurchases'
        );

        Route::delete(
            '/{agentCollection}/purchases/{coffeePurchase}',
            'removePurchase'
        );

        Route::patch(
            '/{agentCollection}/complete',
            'complete'
        );

        Route::patch(
            '/{agentCollection}/cancel',
            'cancel'
        );
    });


Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('agent-collections')
    ->controller(
        \App\Http\Controllers\API\AgentCollection\AgentCollectionController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');
        Route::get('/lookup', 'lookup');

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get('/{agentCollection}', 'show');
        Route::put('/{agentCollection}', 'update');

        Route::post(
            '/{agentCollection}/purchases',
            'addPurchases'
        );

        Route::delete(
            '/{agentCollection}/purchases/{coffeePurchase}',
            'removePurchase'
        );

        Route::patch(
            '/{agentCollection}/complete',
            'complete'
        );

        Route::patch(
            '/{agentCollection}/cancel',
            'cancel'
        );
    });


Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('field-weighings')
    ->controller(
        \App\Http\Controllers\API\FieldWeighing\FieldWeighingController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/eligible-collections',
            'eligibleCollections'
        );

        Route::get('/', 'index');

        Route::post('/', 'store');

        Route::get(
            '/{fieldWeighing}',
            'show'
        );

        Route::put(
            '/{fieldWeighing}',
            'update'
        );

        Route::patch(
            '/{fieldWeighing}/confirm',
            'confirm'
        );

        Route::patch(
            '/{fieldWeighing}/cancel',
            'cancel'
        );
    });



Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('factory-receptions')
    ->controller(
        \App\Http\Controllers\API\FactoryReception\FactoryReceptionController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/eligible-weighings',
            'eligibleWeighings'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{factoryReception}',
            'show'
        );

        Route::put(
            '/{factoryReception}',
            'update'
        );

        Route::patch(
            '/{factoryReception}/confirm',
            'confirm'
        );

        Route::patch(
            '/{factoryReception}/cancel',
            'cancel'
        );
    });


Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('coffee-lots')
    ->controller(
        \App\Http\Controllers\API\CoffeeLot\CoffeeLotController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');
        Route::get('/eligible-sources', 'eligibleSources');

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get('/{coffeeLot}', 'show');
        Route::put('/{coffeeLot}', 'update');

        Route::patch(
            '/{coffeeLot}/close',
            'close'
        );
    });


Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('collection-trips')
    ->controller(
        \App\Http\Controllers\API\CollectionTrip\CollectionTripController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/eligible-weighings',
            'eligibleWeighings'
        );

        Route::get(
            '/driver-lookup',
            'driverLookup'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{collectionTrip}',
            'show'
        );

        Route::put(
            '/{collectionTrip}',
            'update'
        );

        Route::patch(
            '/{collectionTrip}/start',
            'start'
        );

        Route::patch(
            '/{collectionTrip}/arrive',
            'arrive'
        );

        Route::patch(
            '/{collectionTrip}/complete',
            'complete'
        );

        Route::patch(
            '/{collectionTrip}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Driver & Vehicle Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('transport')
    ->group(function () {
        Route::prefix('drivers')
            ->controller(
                \App\Http\Controllers\API\Transport\DriverController::class
            )
            ->group(function () {
                Route::get('/summary', 'summary');
                Route::get('/lookup', 'lookup');
                Route::get('/me', 'me');

                Route::get('/', 'index');
                Route::post('/', 'store');

                Route::get('/{driver}', 'show');
                Route::put('/{driver}', 'update');

                Route::patch(
                    '/{driver}/status',
                    'changeStatus'
                );
            });

        Route::prefix('vehicles')
            ->controller(
                \App\Http\Controllers\API\Transport\VehicleController::class
            )
            ->group(function () {
                Route::get('/summary', 'summary');
                Route::get('/lookup', 'lookup');
                Route::get('/my', 'myVehicle');

                Route::get('/', 'index');
                Route::post('/', 'store');

                Route::get('/{vehicle}', 'show');
                Route::put('/{vehicle}', 'update');

                Route::patch(
                    '/{vehicle}/assign-driver',
                    'assignDriver'
                );

                Route::patch(
                    '/{vehicle}/unassign-driver',
                    'unassignDriver'
                );

                Route::patch(
                    '/{vehicle}/status',
                    'changeStatus'
                );
            });
    });


/*
|--------------------------------------------------------------------------
| Driver & Vehicle Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('transport')
    ->group(function () {
        Route::prefix('drivers')
            ->controller(
                \App\Http\Controllers\API\Transport\DriverController::class
            )
            ->group(function () {
                Route::get('/summary', 'summary');
                Route::get('/lookup', 'lookup');
                Route::get('/me', 'me');

                Route::get('/', 'index');
                Route::post('/', 'store');

                Route::get('/{driver}', 'show');
                Route::put('/{driver}', 'update');

                Route::patch(
                    '/{driver}/status',
                    'changeStatus'
                );
            });

        Route::prefix('vehicles')
            ->controller(
                \App\Http\Controllers\API\Transport\VehicleController::class
            )
            ->group(function () {
                Route::get('/summary', 'summary');
                Route::get('/lookup', 'lookup');
                Route::get('/my', 'myVehicle');

                Route::get('/', 'index');
                Route::post('/', 'store');

                Route::get('/{vehicle}', 'show');
                Route::put('/{vehicle}', 'update');

                Route::patch(
                    '/{vehicle}/assign-driver',
                    'assignDriver'
                );

                Route::patch(
                    '/{vehicle}/unassign-driver',
                    'unassignDriver'
                );

                Route::patch(
                    '/{vehicle}/status',
                    'changeStatus'
                );
            });
    });


/*
|--------------------------------------------------------------------------
| Weight Reconciliation Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('weight-reconciliations')
    ->controller(
        \App\Http\Controllers\API\WeightReconciliation\WeightReconciliationController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/eligible-receptions',
            'eligibleReceptions'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{weightReconciliation}',
            'show'
        );

        Route::put(
            '/{weightReconciliation}',
            'update'
        );

        Route::patch(
            '/{weightReconciliation}/reconcile',
            'reconcile'
        );

        Route::patch(
            '/{weightReconciliation}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Store Inventory Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('store-inventories')
    ->controller(
        \App\Http\Controllers\API\StoreInventory\StoreInventoryController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/eligible-lots',
            'eligibleLots'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{storeInventory}',
            'show'
        );

        Route::put(
            '/{storeInventory}',
            'update'
        );

        Route::patch(
            '/{storeInventory}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Stock Movement Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('stock-movements')
    ->controller(
        \App\Http\Controllers\API\StockMovement\StockMovementController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/inventory-lookup',
            'inventoryLookup'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{stockMovement}',
            'show'
        );

        Route::patch(
            '/{stockMovement}/reverse',
            'reverse'
        );
    });


/*
|--------------------------------------------------------------------------
| Coffee Processing Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('processing-batches')
    ->controller(
        \App\Http\Controllers\API\Processing\ProcessingBatchController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/inventory-lookup',
            'inventoryLookup'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{processingBatch}',
            'show'
        );

        Route::put(
            '/{processingBatch}',
            'update'
        );

        Route::patch(
            '/{processingBatch}/start',
            'start'
        );

        Route::patch(
            '/{processingBatch}/complete',
            'complete'
        );

        Route::patch(
            '/{processingBatch}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Processing Yield & Loss Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('processing-yields')
    ->controller(
        \App\Http\Controllers\API\ProcessingYield\ProcessingYieldController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/eligible-batches',
            'eligibleBatches'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{processingYield}',
            'show'
        );

        Route::put(
            '/{processingYield}',
            'update'
        );

        Route::patch(
            '/{processingYield}/confirm',
            'confirm'
        );

        Route::patch(
            '/{processingYield}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Expense Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('expenses')
    ->controller(
        \App\Http\Controllers\API\Expense\ExpenseController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/categories',
            'categories'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{expense}',
            'show'
        );

        Route::put(
            '/{expense}',
            'update'
        );

        Route::patch(
            '/{expense}/record',
            'record'
        );

        Route::patch(
            '/{expense}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Petty Cash Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('petty-cash')
    ->controller(
        \App\Http\Controllers\API\PettyCash\PettyCashController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{pettyCashTransaction}',
            'show'
        );

        Route::patch(
            '/{pettyCashTransaction}/reverse',
            'reverse'
        );
    });


/*
|--------------------------------------------------------------------------
| Payroll Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('payrolls')
    ->controller(
        \App\Http\Controllers\API\Payroll\PayrollController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get(
            '/employee-lookup',
            'employeeLookup'
        );

        Route::get('/', 'index');
        Route::post('/', 'store');

        Route::get(
            '/{payroll}',
            'show'
        );

        Route::put(
            '/{payroll}',
            'update'
        );

        Route::patch(
            '/{payroll}/process',
            'process'
        );

        Route::patch(
            '/{payroll}/pay',
            'pay'
        );

        Route::patch(
            '/{payroll}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Approval Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('approvals')
    ->controller(
        \App\Http\Controllers\API\Approval\ApprovalController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::post(
            '/payrolls/{payroll}/payment',
            'requestPayrollPayment'
        );

        Route::get('/', 'index');

        Route::get(
            '/{approvalRequest}',
            'show'
        );

        Route::patch(
            '/{approvalRequest}/approve',
            'approve'
        );

        Route::patch(
            '/{approvalRequest}/reject',
            'reject'
        );

        Route::patch(
            '/{approvalRequest}/cancel',
            'cancel'
        );
    });


/*
|--------------------------------------------------------------------------
| Notification Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('notifications')
    ->controller(
        \App\Http\Controllers\API\Notification\NotificationController::class
    )
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::patch(
            '/read-all',
            'markAllRead'
        );

        Route::get('/', 'index');

        Route::get(
            '/{systemNotification}',
            'show'
        );

        Route::patch(
            '/{systemNotification}/read',
            'markRead'
        );

        Route::patch(
            '/{systemNotification}/unread',
            'markUnread'
        );
    });


/*
|--------------------------------------------------------------------------
| Report Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('reports')
    ->controller(
        \App\Http\Controllers\API\Report\ReportController::class
    )
    ->group(function () {
        Route::get(
            '/overview',
            'overview'
        );

        Route::get(
            '/finance',
            'finance'
        );

        Route::get(
            '/payroll',
            'payroll'
        );

        Route::get(
            '/approvals',
            'approvals'
        );

        Route::get(
            '/operations',
            'operations'
        );
    });


/*
|--------------------------------------------------------------------------
| Audit Trail
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('audit-logs')
    ->controller(
        \App\Http\Controllers\API\Audit\AuditLogController::class
    )
    ->group(function () {
        Route::get(
            '/summary',
            'summary'
        );

        Route::get(
            '/',
            'index'
        );

        Route::get(
            '/{auditLog}',
            'show'
        );
    });


/* GIHOMBO_DASHBOARD_LIVE_V1 */
\Illuminate\Support\Facades\Route::middleware([
    'auth:sanctum',
    'active.user',
    'password.changed',
])
    ->prefix('dashboard')
    ->group(function (): void {
        \Illuminate\Support\Facades\Route::get(
            'overview',
            [
                \App\Http\Controllers\API\Dashboard\DashboardController::class,
                'overview',
            ]
        );
    });
