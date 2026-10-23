<?php

use App\Http\Controllers\Api\SalesMobile\{CustomerPOController, OrderController, QuotationController, ScheduleController};
use Illuminate\Support\Facades\Route;

/** @var string $routeNamePrefix */
$routeNamePrefix = $routeNamePrefix ?? 'mobile';

$quotationReviewController = $routeNamePrefix === 'mobile'
    ? \App\Http\Controllers\Api\MobileAdminQuotationController::class
    : QuotationController::class;
Route::get('quotations', [$quotationReviewController, 'index'])->name($routeNamePrefix.'.quotations.index');
Route::get('quotations/{quotation}', [$quotationReviewController, 'show'])->whereNumber('quotation')->name($routeNamePrefix.'.quotations.show');
Route::apiResource('quotations', QuotationController::class)->except(['index', 'show'])->names($routeNamePrefix.'.quotations')->where(['quotation' => '[0-9]+']);
Route::post('quotations/{quotation}/submit', [QuotationController::class, 'submit'])->whereNumber('quotation');
Route::post('quotations/{quotation}/review', [$quotationReviewController, 'review'])->whereNumber('quotation');
Route::post('customer-pos/{customer_po}/convert-salesorder', [CustomerPOController::class, 'convert'])->whereNumber('customer_po');
Route::apiResource('customer-pos', CustomerPOController::class)->names($routeNamePrefix.'.customer-pos')->where(['customer_po' => '[0-9]+']);
Route::apiResource('orders', OrderController::class)->names($routeNamePrefix.'.orders')->where(['order' => '[0-9]+']);
Route::get('orders/{order}/batches', [OrderController::class, 'batches'])->whereNumber('order');
Route::get('orders/{order}/dispatches', [OrderController::class, 'dispatches'])->whereNumber('order');
Route::get('batch-schedules/options', [ScheduleController::class, 'options']);
Route::get('batch-schedules', [ScheduleController::class, 'listing']);
Route::get('batch-schedules/{schedule}', [ScheduleController::class, 'detail'])->whereNumber('schedule');
Route::post('batch-schedules', [ScheduleController::class, 'store']);
Route::put('batch-schedules/{schedule}', [ScheduleController::class, 'change'])->whereNumber('schedule');
Route::delete('batch-schedules/{schedule}', [ScheduleController::class, 'remove'])->whereNumber('schedule');
