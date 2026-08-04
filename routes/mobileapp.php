<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MobileApiController;

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
            Route::resource('users', \App\Http\Controllers\UserController::class);
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
