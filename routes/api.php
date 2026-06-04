<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\BillingApiController;
    use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\ProductionOrderApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/send-otp', [AuthApiController::class, 'sendOtp'])->middleware('throttle:6,1');
Route::post('/verify-otp', [AuthApiController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('/resend-verification-email', [AuthApiController::class, 'resendVerificationEmail'])->middleware('throttle:6,1');

Route::post('/gps/telemetry', [\App\Http\Controllers\Api\GpsTelemetryController::class, 'ingest'])->middleware('throttle:120,1');

Route::prefix('auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\LoginController::class, 'login']);
});



Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/sales-summary', [\App\Http\Controllers\Api\DashboardController::class, 'salesSummary']);
        Route::get('/sales-details', [\App\Http\Controllers\Api\DashboardController::class, 'salesDetails']);
        Route::get('/stock-details', [\App\Http\Controllers\Api\DashboardController::class, 'stockDetails']);
        Route::get('/dispatch-batching-summary', [\App\Http\Controllers\Api\DashboardController::class, 'dispatchBatchingSummary']);
        Route::get('/dispatch-details', [\App\Http\Controllers\Api\DashboardController::class, 'dispatchDetails']);
        Route::get('/top-mix-designs', [\App\Http\Controllers\Api\DashboardController::class, 'topMixDesigns']);
        Route::get('/customer-details', [\App\Http\Controllers\Api\DashboardController::class, 'customerDetails']);
        // Route::get('/sales-stats', [\App\Http\Controllers\Api\DashboardController::class, 'salesStats']);
        // Route::get('/top-products', [\App\Http\Controllers\Api\DashboardController::class, 'topProducts']);
        // Route::get('/dispatch-sales-amount', [\App\Http\Controllers\Api\DashboardController::class, 'dispatchSalesAmount']);
        // Route::get('/trips-details', [\App\Http\Controllers\Api\DashboardController::class, 'tripsDetails']);
        // Route::get('/alerts', [\App\Http\Controllers\Api\DashboardController::class, 'alerts']);
    });
    
    Route::get('/master/plants', [\App\Http\Controllers\Api\DashboardController::class, 'plants']);
    
    Route::get('/auth/ensure-key', [AuthApiController::class, 'ensureApiKey']);
    Route::post('/auth/logout', [\App\Http\Controllers\Api\LoginController::class, 'logout']);
});

Route::middleware(['api.key', 'throttle:60,1'])->group(function () {
    Route::post('/auth/regenerate-key', [AuthApiController::class, 'regenerateApiKey']);

    Route::post('/production__Order__data', [ProductionOrderApiController::class, 'store']);
    Route::post('/production/batch', [\App\Http\Controllers\Api\ProductionApiController::class, 'store']);

    Route::post('/dashboard', [DashboardApiController::class, 'index']);
    Route::post('/billing/generate', [BillingApiController::class, 'generate']);
    Route::post('/billing/history', [BillingApiController::class, 'history']);
    Route::post('/billing/{billing}/pay', [BillingApiController::class, 'mockPay']);
});

Route::get('/getuserdetails', function (Request $request) {
    $token = $request->query('params');
    
    if (!$token) {
        return response()->json(['error' => 'params missing'], 400);
    }

    if ($token !== '9cf8e11ee9b35bc5ce21bb4c90bd6fbbf6158d348a27a8581eebc9535eb04d2f') {
        return response()->json(['error' => 'Invalid token'], 401);
    }

    // Fetch all user details
    $users = \App\Models\User::select(['mm_users.id', 'mm_users.username', 'mm_users.password as pass', 'mm_users.mobile', 'mm_users.email'])->with(['personnel', 'roles', 'entityUsers'])->get();

    return response()->json([
        'success' => true,
        'data' => $users
    ]);
});

// Route::get('/batch', function () {
//     return response()->json([
//         "plant_type" => "CP 30",
//         "plant_sl" => "474",
//         "order_no" => "01",
//         "batch_no" => "4",
//         "cust_id" => "01",
//         "site_id" => "PANCHSHIL",
//         "truck_id" => "MH26BE8292",
//         "driver" => "DATTA",
//         "start" => "2023-12-16 07:41:54",
//         "end" => "2023-12-16 08:01:48",
//         "rec_id" => "M30",
//         "rec_name" => "M30",
//         "qty" => "7.0002",
//         "mat" => [
//             ["item" => "10MM", "act" => 3629],
//             ["item" => "Sand", "act" => 6622],
//             ["item" => "20MM", "act" => 4544],
//             ["item" => "CEM2", "act" => 2109],
//              ["item" => "CEM2", "act" => 2129],
//             ["item" => "WATER", "act" => 1546]
//         ]
//     ]);
// });

// Route::get('/test-batch', function (Request $request) {
//     // Note: Http::get('http://127.0.0.1:8000/api/batch') hangs on `php artisan serve` 
//     // because the built-in server is single-threaded and blocks itself. 
//     // Returning the mock payload directly for local testing!
//     return response()->json([
//         "plant_type" => "CP 30",
//         "plant_sl" => "474",
//         "order_no" => "01",
//         "batch_no" => $request->query('batch_no', '4'),
//         "cust_id" => $request->query('cust_id', 'C0001'),
//         "site_id" => "PANCHSHIL",
//         "truck_id" => "MH26BE8292",
//         "driver" => "DATTA",
//         "start" => "2023-12-16 07:41:54",
//         "end" => "2023-12-16 08:01:48",
//         "rec_id" => $request->query('rec_id', 'M20 GRD'),
//         "rec_name" => $request->query('rec_id', 'M20 GRD'),
//         "qty" => "7.0002",
//         "mat" => [
//             ["item" => "12 MM", "act" => 3.629],
//             ["item" => "Sand", "act" => 1.622],
//             // ["item" => "20MM", "act" => 1.144],
//             ["item" => "RAMCO", "act" => 9.709],
//             ["item" => "UltraTech", "act" => 9.509],
//             ["item" => "WATER", "act" => 10.346]
//         ]
//     ]);
// });