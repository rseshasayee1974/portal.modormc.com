<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 18:05:42 Asia/Calcutta (UTC+05:30)
*/

use App\Http\Controllers\Api\SalesMobile\AuthController;
use App\Http\Controllers\Api\SalesMobile\AttendanceController;
use App\Http\Controllers\Api\SalesMobile\DocumentationController;
use App\Http\Controllers\Api\SalesMobile\LeadController;
use App\Http\Controllers\Api\SalesMobile\NotificationController;
use App\Http\Controllers\Api\SalesMobile\OrderController;
use App\Http\Controllers\Api\SalesMobile\QuotationController;
use App\Http\Controllers\Api\SalesMobile\ScheduleController;
use App\Http\Controllers\Api\SalesMobile\WorkspaceController;
use App\Http\Middleware\MobileDeviceToken;
use App\Http\Middleware\MobilePlantContext;
use App\Http\Middleware\MobileSalesScope;
use App\Http\Middleware\TitleCaseInputs;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->withoutMiddleware(TitleCaseInputs::class)->group(function () {
    Route::get('docs', [DocumentationController::class, 'index'])->name('sales-mobile.docs');
    Route::get('openapi.json', [DocumentationController::class, 'specification'])->name('sales-mobile.openapi');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1,sales-mobile-login');
    Route::post('auth/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1,sales-mobile-send-otp');
    Route::post('auth/verify-otp', [AuthController::class, 'verify'])->middleware('throttle:10,1,sales-mobile-otp');
    Route::middleware(['auth:sanctum', MobileDeviceToken::class, 'throttle:120,1,sales-mobile-device'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::get('plants', [AuthController::class, 'plants']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::middleware(MobilePlantContext::class)->prefix('attendances')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::get('current', [AttendanceController::class, 'current']);
            Route::post('check-in', [AttendanceController::class, 'checkIn']);
            Route::post('{attendance}/check-out', [AttendanceController::class, 'checkOut'])->whereNumber('attendance');
            Route::get('{attendance}', [AttendanceController::class, 'show'])->whereNumber('attendance');
        });
        Route::middleware(MobilePlantContext::class)->prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::get('connection', [NotificationController::class, 'connection']);
            Route::post('broadcasting/auth', [NotificationController::class, 'broadcastAuth'])->name('sales-mobile.notifications.broadcast-auth');
            Route::post('read-all', [NotificationController::class, 'readAll']);
            Route::post('{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification');
            Route::post('{notification}/delivered', [NotificationController::class, 'delivered'])->whereNumber('notification');
        });
        // Personal business-record scope applies to every mobile role, including administrators.
        Route::middleware([MobilePlantContext::class, MobileSalesScope::class])->group(function () {
            Route::get('dashboard', [WorkspaceController::class, 'dashboard']);
            Route::get('lookups', [WorkspaceController::class, 'lookups']);
            Route::get('mix-designs', [WorkspaceController::class, 'mixDesigns']);
            Route::get('taxes', [WorkspaceController::class, 'taxes']);
            Route::get('marketing/summary', [WorkspaceController::class, 'marketing']);
            Route::get('customers', [WorkspaceController::class, 'customers']);
            Route::get('customers/{customer}', [WorkspaceController::class, 'customer'])->whereNumber('customer');
            Route::get('sites', [WorkspaceController::class, 'sites']);
            Route::get('sites/{site}', [WorkspaceController::class, 'site'])->whereNumber('site');
            Route::get('leads/statuses', [LeadController::class, 'statuses']);
            Route::get('leads/{lead}/statuses', [LeadController::class, 'statuses'])->whereNumber('lead');
            Route::apiResource('leads', LeadController::class)->names('sales-mobile.leads')->where(['lead' => '[0-9]+']);
            Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->whereNumber('lead');
            Route::post('leads/{lead}/approve', [LeadController::class, 'approve'])->whereNumber('lead');
            Route::get('follow-ups', [LeadController::class, 'followups']);
            Route::post('follow-ups', [LeadController::class, 'saveActivity']);
            Route::put('follow-ups/{activity}', [LeadController::class, 'saveActivity'])->whereNumber('activity');
            Route::post('follow-ups/{activity}/complete', [LeadController::class, 'complete'])->whereNumber('activity');
            Route::delete('follow-ups/{activity}', [LeadController::class, 'deleteActivity'])->whereNumber('activity');
            Route::post('leads/{lead}/quotation', [QuotationController::class, 'createFromLead'])->whereNumber('lead')->name('sales-mobile.leads.quotation');
            $routeNamePrefix = 'sales-mobile';
            require __DIR__.'/mobile-commercial.php';
        });
    });
});
