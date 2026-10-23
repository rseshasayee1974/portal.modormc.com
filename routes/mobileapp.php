<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MobileApiController;

require __DIR__.'/sales-mobile.php';

/*
|--------------------------------------------------------------------------
| Mobile Application API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by bootstrap/app.php inside the "api" middleware group
| and are automatically prefixed with "api/mobile".
|
*/

// Public routes
Route::get('/status', [MobileApiController::class, 'status']);
Route::post('/login', [MobileApiController::class, 'login']);
Route::post('/send-otp', [MobileApiController::class, 'sendOtp'])->middleware('throttle:6,1');
Route::post('/verify-otp', [MobileApiController::class, 'verifyOtp'])->middleware('throttle:10,1');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', \App\Http\Controllers\UserController::class);
    Route::get('/user', [MobileApiController::class, 'user']);
    Route::get('/dashboard', [MobileApiController::class, 'dashboard']);
    Route::get('/sales-summary', [MobileApiController::class, 'salesSummary']);
    Route::get('/sales-details', [MobileApiController::class, 'salesDetails']);
    Route::get('/customer-details', [MobileApiController::class, 'customerDetails']);
    Route::get('/top-mix-designs', [MobileApiController::class, 'topMixDesigns']);
    Route::get('/truck-dispatch-details', [MobileApiController::class, 'dispatchDetailsByTruck']);
    Route::get('/dispatch-details', [MobileApiController::class, 'dispatchDetails']);
    Route::get('/dispatch-batching-summary', [MobileApiController::class, 'dispatchBatchingSummary']);
    Route::get('/stock-details', [MobileApiController::class, 'stockDetails']);
});


// Commercial workflows shared with the sales app; authentication remains app-specific.
Route::middleware(['auth:sanctum', \App\Http\Middleware\MobileAppPlantContext::class, \App\Http\Middleware\MobileSalesScope::class])
    ->withoutMiddleware(\App\Http\Middleware\TitleCaseInputs::class)
    ->group(function () {
        Route::get('leads/statuses', [\App\Http\Controllers\Api\MobileAdminLeadController::class, 'statuses']);
        Route::get('leads/{lead}/statuses', [\App\Http\Controllers\Api\MobileAdminLeadController::class, 'statuses'])->whereNumber('lead');
        Route::apiResource('leads', \App\Http\Controllers\Api\MobileAdminLeadController::class)
            ->only(['index', 'show', 'update'])->names('mobile.leads')->where(['lead' => '[0-9]+']);
        Route::post('leads/{lead}/approve', [\App\Http\Controllers\Api\MobileAdminLeadController::class, 'approve'])->whereNumber('lead');
        $routeNamePrefix = 'mobile';
        require __DIR__.'/mobile-commercial.php';
        Route::get('dropdowns', [\App\Http\Controllers\Api\SalesMobile\WorkspaceController::class, 'lookups']);
        Route::get('customers', [\App\Http\Controllers\Api\SalesMobile\WorkspaceController::class, 'customers']);
        Route::get('sites', [\App\Http\Controllers\Api\SalesMobile\WorkspaceController::class, 'sites']);
        Route::get('mix-designs', [\App\Http\Controllers\Api\SalesMobile\WorkspaceController::class, 'mixDesigns']);
        Route::get('taxes', [\App\Http\Controllers\Api\SalesMobile\WorkspaceController::class, 'taxes']);
    });
