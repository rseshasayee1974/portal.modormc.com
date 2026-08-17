<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SaasDashboardController;
use App\Http\Controllers\PurchaseOrderInwardController;
use App\Http\Controllers\PurchaseOrderController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/register', function () {
    return redirect()->route('login');
})->name('register');

// OTP Verification
Route::middleware(['auth', config('jetstream.auth_session')])->group(function () {
    Route::get('/verifyotp', [\App\Http\Controllers\OtpController::class, 'show'])->name('otp.show');
    Route::post('/verifyotp', [\App\Http\Controllers\OtpController::class, 'verify'])->name('otp.verify');
    Route::post('/resendotp', [\App\Http\Controllers\OtpController::class, 'resend'])->name('otp.resend');
});

Route::middleware([
    'auth',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/session-ping', function () {
        return response()->json(['status' => 'active']);
    })->name('session.ping');

    Route::get('/dashboard', [\App\Http\Controllers\ERPDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/analytics', [\App\Http\Controllers\ERPDashboardController::class, 'analytics'])->name('dashboard.analytics');
    Route::get('dashboard/data', [\App\Http\Controllers\ERPDashboardController::class, 'getData'])->name('dashboard.data');
    Route::get('dashboard/data/metrics', [\App\Http\Controllers\ERPDashboardController::class, 'getMetricsData'])->name('dashboard.data.metrics');
    Route::get('dashboard/data/finance-trend', [\App\Http\Controllers\ERPDashboardController::class, 'getFinanceTrendData'])->name('dashboard.data.finance-trend');
    Route::get('dashboard/data/dispatch-status', [\App\Http\Controllers\ERPDashboardController::class, 'getDispatchStatusData'])->name('dashboard.data.dispatch-status');
    Route::get('dashboard/data/customer-leaderboard', [\App\Http\Controllers\ERPDashboardController::class, 'getCustomerLeaderboardData'])->name('dashboard.data.customer-leaderboard');
    Route::get('dashboard/data/stock', [\App\Http\Controllers\ERPDashboardController::class, 'getStockData'])->name('dashboard.data.stock');
    Route::get('dashboard/data/recent-activity', [\App\Http\Controllers\ERPDashboardController::class, 'getRecentActivityData'])->name('dashboard.data.recent-activity');
    Route::get('dashboard/data/feeds', [\App\Http\Controllers\ERPDashboardController::class, 'getFeedsData'])->name('dashboard.data.feeds');
   Route::get('/production/dashboard', [\App\Http\Controllers\BatchingDashboardController::class, 'index'])->name('production.dashboard');
   Route::get('/production/dashboard/data', [\App\Http\Controllers\BatchingDashboardController::class, 'getData'])->name('production.dashboard.data');
    Route::prefix('saas')->group(function () {
        Route::get('/dashboard', [SaasDashboardController::class, 'dashboard'])->name('saas.dashboard');
        Route::get('/billing', [SaasDashboardController::class, 'billing'])->name('saas.billing');
    });

    Route::get('/tools/image-to-gif', function () {
        return Inertia::render('Tools/ImageToGif');
    })->name('tools.image-to-gif');

    // 0. Settings
    Route::get('/settings/customsetting', [\App\Http\Controllers\CustomSettingController::class, 'index'])->name('settings.customsetting');
    Route::post('/settings/customsetting', [\App\Http\Controllers\CustomSettingController::class, 'update'])->name('settings.customsetting.update');
    Route::post('/settings/customsetting/store', [\App\Http\Controllers\CustomSettingController::class, 'store'])->name('settings.customsetting.store');
    Route::delete('/settings/customsetting/{customsetting}', [\App\Http\Controllers\CustomSettingController::class, 'destroy'])->name('settings.customsetting.destroy');

    // 1. Context (Entity/Plant Switcher)
    Route::prefix('context')->group(function () {
        Route::get('/selectentity', [\App\Http\Controllers\EntityContextController::class, 'index'])->name('entity-context.index');
        Route::post('/selectentity', [\App\Http\Controllers\EntityContextController::class, 'store'])->name('entity-context.store');
        Route::post('/selectplant', [\App\Http\Controllers\EntityContextController::class, 'setPlant'])->name('entity-context.set-plant');
        Route::post('/toggle-suspension', [\App\Http\Controllers\EntityContextController::class, 'toggleSuspension'])->name('entity-context.toggle-suspension');
    });

    // 2. Master Data (Definitions)
    Route::prefix('master')->group(function () {
        Route::resource('addresstypes', \App\Http\Controllers\AddressTypeController::class);
        Route::resource('entitytypes', \App\Http\Controllers\EntityTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('contacttypes', \App\Http\Controllers\ContactTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('bankaccounttypes', \App\Http\Controllers\BankAccountTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('countries', \App\Http\Controllers\CountryController::class)->except(['create', 'edit', 'show']);
        Route::resource('paymentstatuses', \App\Http\Controllers\PaymentStatusController::class)->except(['create', 'edit', 'show']);
        Route::resource('currencies', \App\Http\Controllers\CurrencyController::class)->except(['create', 'edit', 'show']);
        Route::resource('invoicestatuses', \App\Http\Controllers\InvoiceStatusController::class)->except(['create', 'edit', 'show']);
        Route::resource('plans', \App\Http\Controllers\PlanController::class)->except(['create', 'edit', 'show']);
        Route::get('statecodes/{state_id}/districts', [\App\Http\Controllers\StateCodeController::class, 'getDistricts'])->name('statecodes.districts');
        Route::get('statecodes/{state_id}/zipcodes', [\App\Http\Controllers\StateCodeController::class, 'getZipcodes'])->name('statecodes.zipcodes');
        Route::resource('statecodes', \App\Http\Controllers\StateCodeController::class)->except(['create', 'edit', 'show']);
        Route::resource('subscriptionstatuses', \App\Http\Controllers\SubscriptionStatusController::class)->except(['create', 'edit', 'show']);
        Route::resource('paymentmethods', \App\Http\Controllers\PaymentMethodController::class)->except(['create', 'edit', 'show']);
        Route::resource('productunits', \App\Http\Controllers\ProductUnitController::class);
    });

    // 3. Tenant / Organization
    Route::prefix('tenant')->group(function () {
        Route::resource('entities', \App\Http\Controllers\EntityController::class)->except(['create', 'edit']);
        Route::delete('entities/{entity}/addresses/{address}', [\App\Http\Controllers\EntityController::class, 'destroyAddress'])->name('entities.addresses.destroy');
        Route::delete('entities/{entity}/contacts/{contact}', [\App\Http\Controllers\EntityController::class, 'destroyContact'])->name('entities.contacts.destroy');
        Route::delete('entities/{entity}/bankaccounts/{bankAccount}', [\App\Http\Controllers\EntityController::class, 'destroyBankAccount'])->name('entities.bank-accounts.destroy');
        Route::delete('entities/{entity}/axes/{tax}', [\App\Http\Controllers\EntityController::class, 'destroyTax'])->name('entities.taxes.destroy');
        Route::get('plants/by-entity', [\App\Http\Controllers\PlantController::class, 'getByEntity'])->name('plants.by-entity');
        Route::resource('plants', \App\Http\Controllers\PlantController::class);
        Route::post('plants/{plant}/initialize', [\App\Http\Controllers\PlantController::class, 'initialize'])->name('plants.initialize');
        Route::post('plants/{plant}/send-credentials', [\App\Http\Controllers\PlantController::class, 'sendCredentials'])->name('plants.send-credentials');
        
        Route::post('plants/{id}/restore', [\App\Http\Controllers\PlantController::class, 'restore'])->name('plants.restore');
        Route::delete('plants/{id}/force-delete', [\App\Http\Controllers\PlantController::class, 'forceDelete'])->name('plants.force-delete');
        
        Route::post('entities/{id}/restore', [\App\Http\Controllers\EntityController::class, 'restore'])->name('entities.restore');
        Route::delete('entities/{id}/force-delete', [\App\Http\Controllers\EntityController::class, 'forceDelete'])->name('entities.force-delete');
    });

    // 4. Settings & Permissions
    Route::prefix('settings')->group(function () {
        Route::resource('notificationemails', \App\Http\Controllers\NotificationEmailController::class)->except(['create', 'edit', 'show']);
        Route::resource('termsconditions', \App\Http\Controllers\TermsConditionController::class);
        Route::resource('permissions', \App\Http\Controllers\PermissionController::class);
        Route::resource('roles', \App\Http\Controllers\RoleController::class);
        Route::resource('menus', \App\Http\Controllers\MenuController::class);
        Route::resource('sites', \App\Http\Controllers\SiteController::class);
        Route::resource('taxes', \App\Http\Controllers\TaxController::class);
        
        // AI Agent Builder
        Route::get('agents', [\App\Http\Controllers\AgentBuilderController::class, 'index'])->name('settings.agents.index');
        Route::get('agents/create', [\App\Http\Controllers\AgentBuilderController::class, 'create'])->name('settings.agents.create');
        Route::post('agents', [\App\Http\Controllers\AgentBuilderController::class, 'store'])->name('settings.agents.store');
        Route::post('agents/test', [\App\Http\Controllers\AgentBuilderController::class, 'test'])->name('settings.agents.test');
        // Agent Chat History
        Route::post('agents/history', [\App\Http\Controllers\AgentBuilderController::class, 'saveHistory'])->name('settings.agents.history.store');
        Route::get('agents/history', [\App\Http\Controllers\AgentBuilderController::class, 'chatHistories'])->name('settings.agents.history.index');
        Route::get('agents/history/{history}', [\App\Http\Controllers\AgentBuilderController::class, 'showHistory'])->name('settings.agents.history.show');

        // Knowledge Base (RAG)
        Route::get('knowledge', [\App\Http\Controllers\KnowledgeController::class, 'index'])->name('knowledge.index');
        Route::post('knowledge', [\App\Http\Controllers\KnowledgeController::class, 'store'])->name('knowledge.store');
        Route::patch('knowledge/{document}/toggle', [\App\Http\Controllers\KnowledgeController::class, 'toggleActive'])->name('knowledge.toggle');
        Route::post('knowledge/{document}/re-embed', [\App\Http\Controllers\KnowledgeController::class, 'reEmbed'])->name('knowledge.re-embed');
        Route::delete('knowledge/{document}', [\App\Http\Controllers\KnowledgeController::class, 'destroy'])->name('knowledge.destroy');

        
        Route::resource('productcategories', \App\Http\Controllers\ProductCategoryController::class);
        // Template Management
        Route::resource('templates', \App\Http\Controllers\PrintTemplateController::class);
        Route::post('templates/assign', [\App\Http\Controllers\PrintTemplateController::class, 'assign'])->name('templates.assign');
        Route::get('templates/{template}/preview', [\App\Http\Controllers\PrintTemplateController::class, 'preview'])->name('templates.preview');
        Route::get('templates/{module}/customize', [\App\Http\Controllers\PrintTemplateController::class, 'customize'])->name('templates.customize');
        Route::post('templates/{module}/customize', [\App\Http\Controllers\PrintTemplateController::class, 'saveCustomization'])->name('templates.save-customization');
        Route::post('templates/{module}/preview-render', [\App\Http\Controllers\PrintTemplateController::class, 'renderLivePreview'])->name('templates.preview-render');
  Route::get('default-accounts', [\App\Http\Controllers\AccountDefaultSettingController::class, 'index'])->name('settings.account-defaults');
        Route::post('default-accounts', [\App\Http\Controllers\AccountDefaultSettingController::class, 'store'])->name('settings.account-defaults.store');

        // Unified Document Printing Engine
        Route::get('print/{module}/{id}/{action?}', [\App\Http\Controllers\PrintController::class, 'handle'])
            ->name('print.document');
            
        Route::get('patrons/dropdown', [\App\Http\Controllers\PatronController::class, 'dropdown'])->name('patrons.dropdown');
        Route::resource('patrons', \App\Http\Controllers\PatronController::class);
        Route::post('patrons/batch', [\App\Http\Controllers\PatronController::class, 'batchStore'])->name('patrons.batchstore');
    });

    // 5. Patrons & Personnel (Membership)
    Route::prefix('membership')->group(function () {
        Route::get('users/{user}/whatsapp-verification', [\App\Http\Controllers\UserController::class, 'whatsappVerificationUrl'])->name('users.whatsapp-verification');
        Route::post('users/{id}/restore', [\App\Http\Controllers\UserController::class, 'restore'])->name('users.restore');
        Route::delete('users/{id}/force-delete', [\App\Http\Controllers\UserController::class, 'forceDelete'])->name('users.force-delete');
        Route::resource('users', \App\Http\Controllers\UserController::class);
        Route::resource('personnel', \App\Http\Controllers\PersonnelController::class);
        
        // HRMS Routes
        Route::resource('departments', \App\Http\Controllers\DepartmentController::class);
        Route::resource('designations', \App\Http\Controllers\DesignationController::class);
        
        Route::resource('shifts', \App\Http\Controllers\ShiftController::class);
        Route::post('shifts/assign', [\App\Http\Controllers\ShiftController::class, 'assignShift'])->name('shifts.assign');
        Route::delete('shifts/assign/{employeeShift}', [\App\Http\Controllers\ShiftController::class, 'removeShiftAssignment'])->name('shifts.unassign');
        
        Route::resource('attendances', \App\Http\Controllers\AttendanceController::class);
        Route::resource('leave-types', \App\Http\Controllers\LeaveTypeController::class);
        
        Route::resource('leave-applications', \App\Http\Controllers\LeaveApplicationController::class);
        Route::post('leave-applications/{leaveApplication}/approve', [\App\Http\Controllers\LeaveApplicationController::class, 'approve'])->name('leave-applications.approve');
        
        Route::resource('salary-components', \App\Http\Controllers\SalaryComponentController::class);
        Route::resource('payroll-periods', \App\Http\Controllers\PayrollPeriodController::class);
        Route::post('payslips/generate', [\App\Http\Controllers\PayslipController::class, 'generate'])->name('payslips.generate');
        Route::get('payslips/export-ecr', [\App\Http\Controllers\PayslipController::class, 'exportEcr'])->name('payslips.export-ecr');
        Route::get('payslips/export-esic', [\App\Http\Controllers\PayslipController::class, 'exportEsic'])->name('payslips.export-esic');
        Route::resource('payslips', \App\Http\Controllers\PayslipController::class);
    });

    // 6. Orders & Sales
    Route::prefix('orders')->group(function () {
        
        Route::resource('quotations', \App\Http\Controllers\QuotationController::class);
        Route::get('quotations/{quotation}/download', [\App\Http\Controllers\QuotationController::class, 'downloadPdf'])->name('quotations.download');
        Route::get('quotations/{quotation}/report', [\App\Http\Controllers\QuotationController::class, 'report'])->name('quotations.report');
        Route::patch('quotations/{quotation}/convert', [\App\Http\Controllers\QuotationController::class, 'updateConversionStatus'])->name('quotations.convert');
        Route::post('quotations/{quotation}/send-email', [\App\Http\Controllers\QuotationController::class, 'sendEmail'])->name('quotations.send-email');
        Route::get('customer-po', [\App\Http\Controllers\CustomerPOController::class, 'index'])->name('customer-po.index');
        Route::post('customer-po', [\App\Http\Controllers\CustomerPOController::class, 'store'])->name('customer-po.store');
        Route::delete('customer-po/{customerPO}', [\App\Http\Controllers\CustomerPOController::class, 'destroy'])->name('customer-po.destroy');
        Route::put('customer-po/{customerPO}', [\App\Http\Controllers\CustomerPOController::class, 'update'])->name('customer-po.update');
        Route::post('customer-po/{customerPO}/convert-salesorder', [\App\Http\Controllers\CustomerPOController::class, 'convertToSalesOrder'])->name('customer-po.convert-salesorder');
        Route::post('customer-po/{customerPO}/dispatches', [\App\Http\Controllers\DispatchController::class, 'storeForSalesOrder'])->name('customer-po.dispatches.store');
        Route::resource('salesorders', \App\Http\Controllers\SalesOrderController::class);
        Route::get('batches/truck-empty-weight', [\App\Http\Controllers\BatchController::class, 'getTruckEmptyWeight'])->name('batches.truck-empty-weight');
        Route::post('batches/truck-empty-weight', [\App\Http\Controllers\BatchController::class, 'storeTruckEmptyWeight'])->name('batches.store-truck-empty-weight');
        Route::resource('batches', \App\Http\Controllers\BatchController::class);
        Route::post('batches/{batch}/send-email', [\App\Http\Controllers\BatchController::class, 'sendEmail'])->name('batches.send-email');
        Route::get('batches/{batchId}/report', [\App\Http\Controllers\BatchController::class, 'report'])->name('batches.report');
        Route::get('batches/{batchId}/download', [\App\Http\Controllers\BatchController::class, 'downloadPdf'])->name('batches.download');
        Route::get('batches/{batch}/token', [\App\Http\Controllers\BatchController::class, 'token'])->name('batches.token');
        Route::get('batches/{batch}/token/download', [\App\Http\Controllers\BatchController::class, 'downloadTokenPdf'])->name('batches.token.download');
        Route::get('batches/{batch}/dispatch-token', [\App\Http\Controllers\BatchController::class, 'dispatchToken'])->name('batches.dispatch-token');
        Route::get('batches/{batch}/dispatch-token/download', [\App\Http\Controllers\BatchController::class, 'downloadDispatchTokenPdf'])->name('batches.dispatch-token.download');
        Route::get('batches/{batch}/delivery-token', [\App\Http\Controllers\BatchController::class, 'deliveryToken'])->name('batches.delivery-token');
        Route::get('batches/{batch}/delivery-token/download', [\App\Http\Controllers\BatchController::class, 'downloadDeliveryTokenPdf'])->name('batches.delivery-token.download');
        Route::get('batches/{batch}/gate-pass', [\App\Http\Controllers\BatchController::class, 'gatePass'])->name('batches.gate-pass');
        Route::get('batches/{batch}/gate-pass/download', [\App\Http\Controllers\BatchController::class, 'downloadGatePassPdf'])->name('batches.gate-pass.download');
        Route::post('batches/{batch}/sync', [\App\Http\Controllers\BatchController::class, 'syncToScheduler'])->name('batches.sync');
        Route::post('batches/ocr', [\App\Http\Controllers\Api\BatchOcrController::class, 'process'])->name('batches.ocr');
        Route::prefix('api/batch-sheets')->group(function () {
            Route::post('upload', [\App\Http\Controllers\Api\BatchSheetUploadController::class, 'upload'])->name('batch-sheets.upload');
            Route::get('{id}/status', [\App\Http\Controllers\Api\BatchSheetUploadController::class, 'status'])->name('batch-sheets.status');
            Route::get('{id}/verify', [\App\Http\Controllers\Api\BatchSheetUploadController::class, 'verify'])->name('batch-sheets.verify');
            Route::post('{id}/save', [\App\Http\Controllers\Api\BatchSheetUploadController::class, 'saveToDatabase'])->name('batch-sheets.save');
            Route::post('{id}/template', [\App\Http\Controllers\Api\BatchSheetUploadController::class, 'saveTemplate'])->name('batch-sheets.save-template');
            Route::delete('{id}', [\App\Http\Controllers\Api\BatchSheetUploadController::class, 'destroy'])->name('batch-sheets.destroy');
        });
        Route::post('weighbridge/alert', [\App\Http\Controllers\Api\WeighbridgeApiController::class, 'sendAlert'])->name('weighbridge.alert');
        Route::get('production/batch', [\App\Http\Controllers\Api\ProductionApiController::class, 'getConsumption'])->name('batches.production');
        Route::get('dispatches/dropdowns', [\App\Http\Controllers\DispatchController::class, 'dropdowns'])->name('dispatches.dropdowns');
        Route::post('dispatches/{dispatch}/generate-invoice', [\App\Http\Controllers\DispatchController::class, 'generateInvoice'])->name('dispatches.generate-invoice');
        Route::delete('dispatches/{dispatch}/delete-invoice', [\App\Http\Controllers\DispatchController::class, 'deleteInvoice'])->name('dispatches.delete-invoice');
        Route::get('dispatches/{dispatch}/whatsapp-url', [\App\Http\Controllers\DispatchController::class, 'whatsappUrl'])->name('dispatches.whatsapp-url');
        Route::resource('dispatches', \App\Http\Controllers\DispatchController::class);
        
        Route::resource('partyrates', \App\Http\Controllers\PartyRateController::class)->except(['create', 'edit', 'show']);
    });

    // 7. Inventory & Production
    Route::prefix('inventory')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\InventoryDashboardController::class, 'index'])->name('inventory.dashboard');
        Route::patch('adjust/{id}', [\App\Http\Controllers\InventoryDashboardController::class, 'adjust'])->name('inventory.adjust');
        Route::resource('purchaseorder', PurchaseOrderController::class)->names('purchaseorder');
        Route::get('purchaseorder/{purchase_order}/download/{template?}', [PurchaseOrderController::class, 'downloadPdf'])->name('purchaseorder.download');
        Route::get('purchaseorder/{purchase_order}/report', [PurchaseOrderController::class, 'report'])->name('purchaseorder.report');
        Route::post('purchaseorder/{purchase_order}/generate-bill', [PurchaseOrderController::class, 'generateBill'])->name('purchaseorder.generate-bill');
        Route::delete('purchaseorder/{purchase_order}/delete-bill', [PurchaseOrderController::class, 'deleteBill'])->name('purchaseorder.delete-bill');
          Route::resource('stock-exhausts', \App\Http\Controllers\StockExhaustController::class);
        Route::get('stocks', [\App\Http\Controllers\StockController::class, 'index'])->name('stocks.index');
        Route::resource('inwards', PurchaseOrderInwardController::class)->except(['create']);
        Route::get('inwards/create/{purchase_order?}', [PurchaseOrderInwardController::class, 'create'])->name('inwards.create');
        Route::post('inwards/{inward}/update-weight', [PurchaseOrderInwardController::class, 'updateWeight'])->name('inwards.update-weight');
       
        Route::resource('products', \App\Http\Controllers\ProductController::class);
        Route::post('products/batch', [\App\Http\Controllers\ProductController::class, 'batchStore'])->name('products.batchstore');
        Route::resource('inventory-audit-logs', \App\Http\Controllers\InventoryAuditLogController::class);
        
        Route::get('mixdesigns/gradeingredients/{gradeId}', [\App\Http\Controllers\MixDesignController::class, 'getGradeIngredients'])->name('mixdesigns.gradeingredients');
        Route::resource('mixdesigns', \App\Http\Controllers\MixDesignController::class);
        Route::resource('concretegrades', \App\Http\Controllers\ConcreteGradeController::class);
        Route::resource('concrete-quality-tests', \App\Http\Controllers\ConcreteQualityTestController::class);
    });

    // 8. Fleet & Personnel Logistics
    Route::prefix('fleet')->group(function () {
        Route::resource('machines', \App\Http\Controllers\MachineController::class);
        Route::resource('machinetypes', \App\Http\Controllers\MachineTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('maintenance-requests', \App\Http\Controllers\MaintenanceRequestController::class);
        Route::resource('machine-services', \App\Http\Controllers\MachineServiceController::class);
        Route::resource('machine-trackers', \App\Http\Controllers\MachineTrackerController::class);
      
        Route::resource('drivers', \App\Http\Controllers\DriverController::class);
        Route::resource('fuel-logs', \App\Http\Controllers\FuelLogController::class);

        // GPS Tracking & Geofences
        Route::resource('gps-devices', \App\Http\Controllers\GpsDeviceController::class);
        Route::resource('geofences', \App\Http\Controllers\GeofenceController::class);
        Route::get('gps/live', [\App\Http\Controllers\GpsTrackingController::class, 'live'])->name('gps.live');
        Route::get('gps/playback', [\App\Http\Controllers\GpsTrackingController::class, 'playback'])->name('gps.playback');
        Route::get('gps/playback-data', [\App\Http\Controllers\GpsTrackingController::class, 'playbackData'])->name('gps.playback-data');
    });
  Route::prefix('reports')->group(function () {
      // Unified Reports
         Route::get('report', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
         Route::get('generate', [\App\Http\Controllers\ReportController::class, 'generate'])->name('reports.generate');
         Route::get('sales-register', [\App\Http\Controllers\ReportController::class, 'salesRegister'])->name('reports.sales-register');
         Route::get('purchase-register', [\App\Http\Controllers\ReportController::class, 'purchaseRegister'])->name('reports.purchase-register');
         Route::get('machine-summary', [\App\Http\Controllers\ReportController::class, 'machineSummary'])->name('reports.machine-summary');
         Route::get('vehicle-pl', [\App\Http\Controllers\ReportController::class, 'vehiclePL'])->name('reports.vehicle-pl');
         Route::get('export-status/{key}', [\App\Http\Controllers\ReportController::class, 'getExportStatus'])->name('reports.export-status');
         Route::get('schedules', [\App\Http\Controllers\ReportController::class, 'listSchedules'])->name('reports.schedules.index');
         Route::post('schedules', [\App\Http\Controllers\ReportController::class, 'storeSchedule'])->name('reports.schedules.store');
         Route::delete('schedules/{schedule}', [\App\Http\Controllers\ReportController::class, 'deleteSchedule'])->name('reports.schedules.destroy');
  });
    // 10. Finance & Accounting
    Route::prefix('finance')->group(function () {
        Route::resource('accounts', \App\Http\Controllers\AccountsController::class);
        Route::resource('accounttypes', \App\Http\Controllers\AccountsTypeController::class);
        Route::get('ledgers/dropdown', [\App\Http\Controllers\LedgerController::class, 'dropdown'])->name('ledgers.dropdown');
        Route::resource('ledgers', \App\Http\Controllers\LedgerController::class);
        Route::get('ledgers/nextcode', [\App\Http\Controllers\LedgerController::class, 'getNextCode'])->name('accounting.nextcode');
        
        Route::resource('vouchertypes', \App\Http\Controllers\VoucherTypeController::class);
        Route::resource('journalentries', \App\Http\Controllers\JournalEntryController::class);
        
        Route::resource('expensetypes', \App\Http\Controllers\ExpenseTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('expenses', \App\Http\Controllers\ExpenseController::class)->only(['index', 'store', 'destroy']);
        
        Route::resource('pettycash', \App\Http\Controllers\PettyCashController::class)->except(['create', 'edit', 'show']);
        Route::post('pettycash/{petty_cash}/close', [\App\Http\Controllers\PettyCashController::class, 'close'])->name('pettycash.close');
        
        // Bank Reconciliation
        Route::get('reconciliation', [\App\Http\Controllers\BankReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::post('reconciliation/upload', [\App\Http\Controllers\BankReconciliationController::class, 'upload'])->name('reconciliation.upload');
        Route::get('reconciliation/lines', [\App\Http\Controllers\BankReconciliationController::class, 'getLines'])->name('reconciliation.lines');
        Route::post('reconciliation/reconcile', [\App\Http\Controllers\BankReconciliationController::class, 'reconcile'])->name('reconciliation.reconcile');
        Route::post('reconciliation/unreconcile', [\App\Http\Controllers\BankReconciliationController::class, 'unreconcile'])->name('reconciliation.unreconcile');
        Route::post('reconciliation/create-voucher', [\App\Http\Controllers\BankReconciliationController::class, 'createVoucher'])->name('reconciliation.create-voucher');
        Route::get('reconciliation/template', [\App\Http\Controllers\BankReconciliationController::class, 'downloadTemplate'])->name('reconciliation.template');

        Route::get('payments/next-reference', [\App\Http\Controllers\PaymentController::class, 'getNextReferenceNumber'])->name('payments.next-reference');
        Route::get('payments/patron-advance-balance', [\App\Http\Controllers\PaymentController::class, 'getPatronAdvanceBalance'])->name('payments.patron-advance-balance');
        Route::resource('payments', \App\Http\Controllers\PaymentController::class)->except(['create', 'edit', 'show']);
        Route::get('billings/unbilled-purchase-orders', [\App\Http\Controllers\BillingController::class, 'getUnbilledPurchaseOrders'])->name('billings.unbilled-pos');
        Route::get('invoices/uninvoiced-dispatches', [\App\Http\Controllers\InvoiceController::class, 'getUninvoicedDispatches'])->name('invoices.uninvoiced-dispatches');

        Route::get('invoices/outstanding', [\App\Http\Controllers\InvoiceController::class, 'outstanding'])->name('invoices.outstanding');
        Route::delete('invoices/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'destroy'])->name('invoices.destroy')->where('invoice', '.*');
        Route::resource('invoices', \App\Http\Controllers\InvoiceController::class)->except(['create', 'edit', 'destroy']);
        
        // E-Invoice & E-Way Bill Compliance
        Route::post('invoices/{invoice}/generate-einvoice', [\App\Http\Controllers\EInvoiceController::class, 'generate'])->name('invoices.generate-einvoice');
        Route::post('invoices/{invoice}/cancel-einvoice', [\App\Http\Controllers\EInvoiceController::class, 'cancel'])->name('invoices.cancel-einvoice');
        Route::post('invoices/{invoice}/generate-ewaybill', [\App\Http\Controllers\EInvoiceController::class, 'generateEWayBill'])->name('invoices.generate-ewaybill');
        Route::post('invoices/{invoice}/cancel-ewaybill', [\App\Http\Controllers\EInvoiceController::class, 'cancelEWayBill'])->name('invoices.cancel-ewaybill');
        Route::post('invoices/{invoice}/setup-demo-compliance', [\App\Http\Controllers\EInvoiceController::class, 'setupDemoCompliance'])->name('invoices.setup-demo-compliance');
        Route::get('compliance/test', [\App\Http\Controllers\EInvoiceController::class, 'testPage'])->name('compliance.test');
        Route::post('compliance/test-action', [\App\Http\Controllers\EInvoiceController::class, 'testAction'])->name('compliance.test-action');

        Route::delete('billings/{billing}', [\App\Http\Controllers\BillingController::class, 'destroy'])->name('billings.destroy')->where('billing', '.*');
        Route::resource('billings', \App\Http\Controllers\BillingController::class)->except(['create', 'edit', 'destroy']);
        
        // Bank Reconciliation (BRS)
        Route::prefix('reconciliation')->group(function () {
            Route::get('/', [\App\Http\Controllers\BankReconciliationController::class, 'index'])->name('reconciliation.index');
            Route::post('/upload', [\App\Http\Controllers\BankReconciliationController::class, 'upload'])->name('reconciliation.upload');
            Route::get('/lines', [\App\Http\Controllers\BankReconciliationController::class, 'getLines'])->name('reconciliation.lines');
            Route::post('/reconcile', [\App\Http\Controllers\BankReconciliationController::class, 'reconcile'])->name('reconciliation.reconcile');
            Route::post('/unreconcile', [\App\Http\Controllers\BankReconciliationController::class, 'unreconcile'])->name('reconciliation.unreconcile');
            Route::post('/create-voucher', [\App\Http\Controllers\BankReconciliationController::class, 'createVoucher'])->name('reconciliation.create-voucher');
        });

        // Public Document Share Token Generation (Authenticated)
        Route::post('invoices/{id}/share', [\App\Http\Controllers\InvoiceShareController::class, 'generateLink'])->name('invoices.share');
        Route::post('reports/share', [\App\Http\Controllers\InvoiceShareController::class, 'generateLink'])->name('reports.share');
        Route::post('batches/{id}/share', [\App\Http\Controllers\InvoiceShareController::class, 'generateLink'])->name('batches.share');
    });

    // Bridge Proxy (Bypass CORS for local hardware)
    Route::get('/bridge/weight', function (\Illuminate\Http\Request $request) {
        try {
            return \Illuminate\Support\Facades\Http::timeout(3)->get('http://localhost:8089/api/port')->body();
        } catch (\Exception $e) {
            return response('Bridge connection failed', 503);
        }
    })->name('bridge.weight');
});

// Public Document Share Access (Guest/No Authentication)
Route::get('public/invoice/{token}', [\App\Http\Controllers\InvoiceShareController::class, 'viewInvoice'])->name('public.invoice.view');
Route::get('public/invoice/{token}/pdf', [\App\Http\Controllers\InvoiceShareController::class, 'downloadPDF'])->name('public.invoice.pdf');
Route::get('public/report/{token}', [\App\Http\Controllers\InvoiceShareController::class, 'viewReport'])->name('public.report.view');
Route::get('public/report/{token}/pdf', [\App\Http\Controllers\InvoiceShareController::class, 'downloadReportPDF'])->name('public.report.pdf');
Route::get('/public/batch/{token}', [\App\Http\Controllers\InvoiceShareController::class, 'viewBatch'])->name('public.batch.view');
Route::get('/public/batch/{token}/pdf', [\App\Http\Controllers\InvoiceShareController::class, 'downloadBatchPDF'])->name('public.batch.pdf');

// Public Gate Pass Verification (Guest)
Route::get('/public/gatepass/verify/{batch}/{hash}', [\App\Http\Controllers\BatchController::class, 'publicVerifyGatePass'])->name('public.gatepass.verify');
Route::post('/public/gatepass/verify/{batch}/{hash}', [\App\Http\Controllers\BatchController::class, 'publicConfirmGatePass'])->name('public.gatepass.confirm');

require __DIR__.'/auth.php';