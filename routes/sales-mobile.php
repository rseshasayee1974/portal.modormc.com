<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:05:42 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SalesMobile\{AuthController, LeadController, QuotationController, OrderController, ScheduleController, WorkspaceController};
use App\Http\Middleware\{MobileDeviceToken, MobilePlantContext};

Route::prefix('v1')->withoutMiddleware(\App\Http\Middleware\TitleCaseInputs::class)->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1,sales-mobile-login');
    Route::post('auth/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1,sales-mobile-send-otp');
    Route::post('auth/verify-otp', [AuthController::class, 'verify'])->middleware('throttle:10,1,sales-mobile-otp');
    Route::middleware(['auth:sanctum', MobileDeviceToken::class, 'throttle:120,1,sales-mobile-device'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::get('plants', [AuthController::class, 'plants']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::middleware(MobilePlantContext::class)->group(function () {
            Route::get('dashboard', [WorkspaceController::class, 'dashboard']);
            Route::get('lookups', [WorkspaceController::class, 'lookups']);
            Route::get('marketing/summary', [WorkspaceController::class, 'marketing']);
            Route::get('customers', [WorkspaceController::class, 'customers']);
            Route::get('customers/{customer}', [WorkspaceController::class, 'customer'])->whereNumber('customer');
            Route::apiResource('leads', LeadController::class)->names('sales-mobile.leads')->where(['lead' => '[0-9]+']);
            Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->whereNumber('lead');
            Route::get('follow-ups', [LeadController::class, 'followups']);
            Route::post('follow-ups', [LeadController::class, 'saveActivity']);
            Route::put('follow-ups/{activity}', [LeadController::class, 'saveActivity'])->whereNumber('activity');
            Route::post('follow-ups/{activity}/complete', [LeadController::class, 'complete'])->whereNumber('activity');
            Route::delete('follow-ups/{activity}', [LeadController::class, 'deleteActivity'])->whereNumber('activity');
            Route::apiResource('quotations', QuotationController::class)->names('sales-mobile.quotations')->where(['quotation' => '[0-9]+']);
            Route::post('quotations/{quotation}/submit', [QuotationController::class, 'submit'])->whereNumber('quotation');
            Route::post('quotations/{quotation}/review', [QuotationController::class, 'review'])->whereNumber('quotation');
            Route::apiResource('orders', OrderController::class)->names('sales-mobile.orders')->where(['order' => '[0-9]+']);
            Route::get('orders/{order}/batches', [OrderController::class, 'batches'])->whereNumber('order');
            Route::get('orders/{order}/dispatches', [OrderController::class, 'dispatches'])->whereNumber('order');
            Route::get('batch-schedules', [ScheduleController::class, 'listing']);
            Route::get('batch-schedules/{schedule}', [ScheduleController::class, 'detail'])->whereNumber('schedule');
            Route::post('batch-schedules', [ScheduleController::class, 'store']);
            Route::put('batch-schedules/{schedule}', [ScheduleController::class, 'change'])->whereNumber('schedule');
            Route::delete('batch-schedules/{schedule}', [ScheduleController::class, 'remove'])->whereNumber('schedule');
        });
    });
});
