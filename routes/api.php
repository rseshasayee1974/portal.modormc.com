<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\BillingApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\ModuleApiController;
use App\Http\Controllers\Api\ProductionOrderApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/send-otp', [AuthApiController::class, 'sendOtp'])->middleware('throttle:6,1');
Route::post('/verify-otp', [AuthApiController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('/resend-verification-email', [AuthApiController::class, 'resendVerificationEmail'])->middleware('throttle:6,1');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthApiController::class, 'register']);
    Route::post('/login', [AuthApiController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/ensure-key', [AuthApiController::class, 'ensureApiKey']);
});

Route::middleware(['api.key', 'throttle:60,1'])->group(function () {
    Route::post('/auth/regenerate-key', [AuthApiController::class, 'regenerateApiKey']);

    Route::post('/chat', [ModuleApiController::class, 'chat']);
    Route::post('/image', [ModuleApiController::class, 'image']);
    Route::post('/search', [ModuleApiController::class, 'search']);
    Route::post('/production__Order__data', [ProductionOrderApiController::class, 'store']);
    Route::post('/production/batch', [\App\Http\Controllers\Api\ProductionApiController::class, 'store']);

    Route::get('/dashboard', [DashboardApiController::class, 'index']);
    Route::post('/billing/generate', [BillingApiController::class, 'generate']);
    Route::get('/billing/history', [BillingApiController::class, 'history']);
    Route::post('/billing/{billing}/pay', [BillingApiController::class, 'mockPay']);
});

Route::get('/batch', function () {
    return response()->json([
        "plant_type" => "CP 30",
        "plant_sl" => "474",
        "order_no" => "01",
        "batch_no" => "4",
        "cust_id" => "01",
        "site_id" => "PANCHSHIL",
        "truck_id" => "MH26BE8292",
        "driver" => "DATTA",
        "start" => "2023-12-16 07:41:54",
        "end" => "2023-12-16 08:01:48",
        "rec_id" => "M30",
        "rec_name" => "M30",
        "qty" => "7.0002",
        "mat" => [
            ["item" => "10MM", "act" => 3629],
            ["item" => "Sand", "act" => 6622],
            ["item" => "20MM", "act" => 4544],
            ["item" => "CEM2", "act" => 2109],
             ["item" => "CEM2", "act" => 2129],
            ["item" => "WATER", "act" => 1546]
        ]
    ]);
});

Route::get('/test-batch', function (Request $request) {
    // Note: Http::get('http://127.0.0.1:8000/api/batch') hangs on `php artisan serve` 
    // because the built-in server is single-threaded and blocks itself. 
    // Returning the mock payload directly for local testing!
    return response()->json([
        "plant_type" => "CP 30",
        "plant_sl" => "474",
        "order_no" => "01",
        "batch_no" => $request->query('batch_no', '4'),
        "cust_id" => $request->query('cust_id', 'C0001'),
        "site_id" => "PANCHSHIL",
        "truck_id" => "MH26BE8292",
        "driver" => "DATTA",
        "start" => "2023-12-16 07:41:54",
        "end" => "2023-12-16 08:01:48",
        "rec_id" => $request->query('rec_id', 'M20 GRD'),
        "rec_name" => $request->query('rec_id', 'M20 GRD'),
        "qty" => "7.0002",
        "mat" => [
            ["item" => "12 MM", "act" => 3.629],
            ["item" => "Sand", "act" => 1.622],
            // ["item" => "20MM", "act" => 1.144],
            ["item" => "RAMCO", "act" => 9.709],
            ["item" => "UltraTech", "act" => 9.509],
            ["item" => "WATER", "act" => 10.346]
        ]
    ]);
});
