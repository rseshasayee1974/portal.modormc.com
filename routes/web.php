<?php

use App\Http\Controllers\AccountDefaultSettingController;
use App\Http\Controllers\AccountDiscountController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\AccountsTypeController;
use App\Http\Controllers\AddressTypeController;
use App\Http\Controllers\AgentBuilderController;
use App\Http\Controllers\Api\BatchOcrController;
use App\Http\Controllers\Api\BatchSheetUploadController;
use App\Http\Controllers\Api\ProductionApiController;
use App\Http\Controllers\Api\WeighbridgeApiController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BankAccountTypeController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BatchingDashboardController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BulkDocumentReportController;
use App\Http\Controllers\ConcreteBatchingScheduleController;
use App\Http\Controllers\ConcreteGradeController;
use App\Http\Controllers\ConcreteQualityTestController;
use App\Http\Controllers\ContactTypeController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CrmLeadController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerPOController;
use App\Http\Controllers\CustomSettingController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\EInvoiceController;
use App\Http\Controllers\EntityContextController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\EntityTypeController;
use App\Http\Controllers\ERPDashboardController;
use App\Http\Controllers\EwayBillController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseTypeController;
use App\Http\Controllers\FuelLogController;
use App\Http\Controllers\GeofenceController;
use App\Http\Controllers\GpsDeviceController;
use App\Http\Controllers\GpsTrackingController;
use App\Http\Controllers\InventoryAuditLogController;
use App\Http\Controllers\InventoryDashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceNoteController;
use App\Http\Controllers\InvoiceShareController;
use App\Http\Controllers\InvoiceStatusController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MachineServiceController;
use App\Http\Controllers\MachineTrackerController;
use App\Http\Controllers\MachineTypeController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MixDesignController;
use App\Http\Controllers\NotificationEmailController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PartyRateController;
use App\Http\Controllers\PatronController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentStatusController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\PlantDataCleanupController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\PrintTemplateController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\PumpBoomDeploymentController;
use App\Http\Controllers\PumpRateController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderInwardController;
use App\Http\Controllers\QC\QCDashboardController;
use App\Http\Controllers\QC\QCMaterialTestMappingController;
use App\Http\Controllers\QC\QcProductGradeConfigController;
use App\Http\Controllers\QC\QCReportController;
use App\Http\Controllers\QC\QCSampleController;
use App\Http\Controllers\QC\QCTestExecutionController;
use App\Http\Controllers\QC\QCTestParameterController;
use App\Http\Controllers\QC\QCTestScheduleController;
use App\Http\Controllers\QC\QCTestTypeController;
use App\Http\Controllers\QC\QCUnitController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaasDashboardController;
use App\Http\Controllers\SalaryComponentController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StateCodeController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockExhaustController;
use App\Http\Controllers\SubscriptionStatusController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TermsConditionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserMobileAccessController;
use App\Http\Controllers\UserPresenceController;
use App\Http\Controllers\VoucherTypeController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireOtpVerification;
use App\Http\Middleware\SetEntityContext;
use App\Http\Middleware\SetEntityTimezone;
use App\Http\Middleware\TitleCaseInputs;
use App\Models\CustomSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    Route::get('/verifyotp', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/verifyotp', [OtpController::class, 'verify'])->name('otp.verify');
    Route::post('/resendotp', [OtpController::class, 'resend'])->name('otp.resend');
});

Route::middleware('auth')->withoutMiddleware([
    SetEntityContext::class,
    SetEntityTimezone::class,
    RequireOtpVerification::class,
    HandleInertiaRequests::class,
])->group(function () {
    Route::post('/session/presence', [UserPresenceController::class, 'update'])->name('session.presence');
    Route::post('/session/presence/close', [UserPresenceController::class, 'update'])->name('session.presence.close');
});

Route::middleware([
    'auth',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/session-ping', function () {
        return response()->json(['status' => 'active']);
    })->name('session.ping');

    Route::get('/dashboard', [ERPDashboardController::class, 'index'])->name('dashboard');
    Route::prefix('crm/leads')->name('crm.leads.')->controller(CrmLeadController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/assign', 'assign')->name('assign');
        Route::get('/{lead}', 'show')->name('show');
        Route::put('/{lead}', 'update')->name('update');
        Route::delete('/{lead}', 'destroy')->name('destroy');
        Route::post('/{lead}/convert', 'convert')->name('convert');
        Route::post('/{lead}/activities', 'activity')->name('activity');
        Route::patch('/{lead}/activities/{activity}/complete', 'complete')->name('complete');
        Route::put('/{lead}/deal', 'deal')->name('deal');
        Route::post('/{lead}/attachments', 'attach')->name('attach');
        Route::get('/{lead}/attachments/{attachment}', 'download')->name('download');
    });
    Route::get('/dashboard/analytics', [ERPDashboardController::class, 'analytics'])->name('dashboard.analytics');
    Route::get('dashboard/data', [ERPDashboardController::class, 'getData'])->name('dashboard.data');
    Route::get('dashboard/data/metrics', [ERPDashboardController::class, 'getMetricsData'])->name('dashboard.data.metrics');
    Route::get('dashboard/data/finance-trend', [ERPDashboardController::class, 'getFinanceTrendData'])->name('dashboard.data.finance-trend');
    Route::get('dashboard/data/dispatch-status', [ERPDashboardController::class, 'getDispatchStatusData'])->name('dashboard.data.dispatch-status');
    Route::get('dashboard/data/customer-leaderboard', [ERPDashboardController::class, 'getCustomerLeaderboardData'])->name('dashboard.data.customer-leaderboard');
    Route::get('dashboard/data/stock', [ERPDashboardController::class, 'getStockData'])->name('dashboard.data.stock');
    Route::get('dashboard/data/recent-activity', [ERPDashboardController::class, 'getRecentActivityData'])->name('dashboard.data.recent-activity');
    Route::get('dashboard/data/feeds', [ERPDashboardController::class, 'getFeedsData'])->name('dashboard.data.feeds');
    Route::get('/production/dashboard', [BatchingDashboardController::class, 'index'])->name('production.dashboard');
    Route::get('/production/dashboard/data', [BatchingDashboardController::class, 'getData'])->name('production.dashboard.data');

    // Concrete Batching & Dispatch Scheduling
    Route::prefix('production/batching-schedules')->name('production.batching-schedules.')->group(function () {
        Route::get('/', [ConcreteBatchingScheduleController::class, 'index'])->name('index');
        Route::get('/data', [ConcreteBatchingScheduleController::class, 'getData'])->name('data');
        Route::get('/dropdowns', [ConcreteBatchingScheduleController::class, 'dropdowns'])->name('dropdowns');
        Route::post('/', [ConcreteBatchingScheduleController::class, 'store'])->name('store');
        Route::put('/{schedule}', [ConcreteBatchingScheduleController::class, 'update'])->name('update');
        Route::patch('/{schedule}/status', [ConcreteBatchingScheduleController::class, 'updateStatus'])->name('update-status');
        Route::post('/{schedule}/create-dispatch', [ConcreteBatchingScheduleController::class, 'createDispatchTicket'])->name('create-dispatch');
        Route::delete('/{schedule}', [ConcreteBatchingScheduleController::class, 'destroy'])->name('destroy');
    });

    // Pump & Boom Deployment Scheduling
    Route::redirect('production/pump-deployments', '/production/pump-schedules');
    Route::prefix('production/pump-schedules')->name('production.pump-deployments.')->group(function () {
        Route::get('/', [PumpBoomDeploymentController::class, 'index'])->name('index');
        Route::get('/data', [PumpBoomDeploymentController::class, 'getData'])->name('data');
        Route::get('/dropdowns', [PumpBoomDeploymentController::class, 'dropdowns'])->name('dropdowns');
        Route::post('/', [PumpBoomDeploymentController::class, 'store'])->name('store');
        Route::put('/{deployment}', [PumpBoomDeploymentController::class, 'update'])->name('update');
        Route::patch('/{deployment}/status', [PumpBoomDeploymentController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{deployment}', [PumpBoomDeploymentController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('saas')->group(function () {
        Route::get('/dashboard', [SaasDashboardController::class, 'dashboard'])->name('saas.dashboard');
        Route::get('/billing', [SaasDashboardController::class, 'billing'])->name('saas.billing');
    });

    Route::get('/tools/image-to-gif', function () {
        return Inertia::render('Tools/ImageToGif');
    })->name('tools.image-to-gif');

    // 0. Settings
    Route::get('/settings/customsetting', [CustomSettingController::class, 'index'])->name('settings.customsetting');
    Route::post('/settings/customsetting', [CustomSettingController::class, 'update'])->withoutMiddleware(TitleCaseInputs::class)->name('settings.customsetting.update');
    Route::post('/settings/customsetting/store', [CustomSettingController::class, 'store'])->withoutMiddleware(TitleCaseInputs::class)->name('settings.customsetting.store');
    Route::delete('/settings/customsetting/{customsetting}', [CustomSettingController::class, 'destroy'])->name('settings.customsetting.destroy');

    // Plant Data Cleanup (Bulk Delete & Restore per Active Plant)
    Route::prefix('settings/plant-data-cleanup')->name('plant-data-cleanup.')->group(function () {
        Route::get('/', [PlantDataCleanupController::class, 'index'])->name('index');
        Route::post('/bulk-delete', [PlantDataCleanupController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('/bulk-restore', [PlantDataCleanupController::class, 'bulkRestore'])->name('bulk-restore');
    });

    // 1. Context (Entity/Plant Switcher)
    Route::prefix('context')->group(function () {
        Route::get('/selectentity', [EntityContextController::class, 'index'])->name('entity-context.index');
        Route::post('/selectentity', [EntityContextController::class, 'store'])->name('entity-context.store');
        Route::post('/selectplant', [EntityContextController::class, 'setPlant'])->name('entity-context.set-plant');
        Route::post('/toggle-suspension', [EntityContextController::class, 'toggleSuspension'])->name('entity-context.toggle-suspension');
    });

    // 2. Master Data (Definitions)
    Route::prefix('master')->group(function () {
        Route::resource('addresstypes', AddressTypeController::class);
        Route::resource('entitytypes', EntityTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('contacttypes', ContactTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('bankaccounttypes', BankAccountTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('countries', CountryController::class)->except(['create', 'edit', 'show']);
        Route::resource('paymentstatuses', PaymentStatusController::class)->except(['create', 'edit', 'show']);
        Route::resource('currencies', CurrencyController::class)->except(['create', 'edit', 'show']);
        Route::resource('invoicestatuses', InvoiceStatusController::class)->except(['create', 'edit', 'show']);
        Route::resource('plans', PlanController::class)->except(['create', 'edit', 'show']);
        Route::get('statecodes/{state_id}/districts', [StateCodeController::class, 'getDistricts'])->name('statecodes.districts');
        Route::get('statecodes/{state_id}/zipcodes', [StateCodeController::class, 'getZipcodes'])->name('statecodes.zipcodes');
        Route::resource('statecodes', StateCodeController::class)->except(['create', 'edit', 'show']);
        Route::resource('subscriptionstatuses', SubscriptionStatusController::class)->except(['create', 'edit', 'show']);
        Route::resource('paymentmethods', PaymentMethodController::class)->except(['create', 'edit', 'show']);
        Route::resource('productunits', ProductUnitController::class);
        Route::resource('leave-types', LeaveTypeController::class);

    });

    // 3. Tenant / Organization
    Route::prefix('tenant')->group(function () {
        Route::resource('entities', EntityController::class)->except(['create', 'edit']);
        Route::delete('entities/{entity}/addresses/{address}', [EntityController::class, 'destroyAddress'])->name('entities.addresses.destroy');
        Route::delete('entities/{entity}/contacts/{contact}', [EntityController::class, 'destroyContact'])->name('entities.contacts.destroy');
        Route::delete('entities/{entity}/bankaccounts/{bankAccount}', [EntityController::class, 'destroyBankAccount'])->name('entities.bank-accounts.destroy');
        Route::delete('entities/{entity}/axes/{tax}', [EntityController::class, 'destroyTax'])->name('entities.taxes.destroy');
        Route::get('plants/by-entity', [PlantController::class, 'getByEntity'])->name('plants.by-entity');
        Route::resource('plants', PlantController::class);
        Route::post('plants/{plant}/initialize', [PlantController::class, 'initialize'])->name('plants.initialize');
        Route::post('plants/{plant}/send-credentials', [PlantController::class, 'sendCredentials'])->name('plants.send-credentials');

        Route::post('plants/{id}/restore', [PlantController::class, 'restore'])->name('plants.restore');
        Route::delete('plants/{id}/force-delete', [PlantController::class, 'forceDelete'])->name('plants.force-delete');

        Route::post('entities/{id}/restore', [EntityController::class, 'restore'])->name('entities.restore');
        Route::delete('entities/{id}/force-delete', [EntityController::class, 'forceDelete'])->name('entities.force-delete');
    });

    // 4. Settings & Permissions
    Route::prefix('settings')->group(function () {
        Route::resource('notificationemails', NotificationEmailController::class)->except(['create', 'edit', 'show']);
        Route::resource('termsconditions', TermsConditionController::class);
        Route::resource('permissions', PermissionController::class);
        Route::resource('roles', RoleController::class);
        Route::resource('menus', MenuController::class);
        Route::get('sites/address-options', [SiteController::class, 'addressOptions'])->name('sites.address-options');
        Route::resource('sites', SiteController::class);
        Route::resource('taxes', TaxController::class);

        // AI Agent Builder
        Route::get('agents', [AgentBuilderController::class, 'index'])->name('settings.agents.index');
        Route::get('agents/create', [AgentBuilderController::class, 'create'])->name('settings.agents.create');
        Route::post('agents', [AgentBuilderController::class, 'store'])->name('settings.agents.store');
        Route::post('agents/test', [AgentBuilderController::class, 'test'])->name('settings.agents.test');
        // Agent Chat History
        Route::post('agents/history', [AgentBuilderController::class, 'saveHistory'])->name('settings.agents.history.store');
        Route::get('agents/history', [AgentBuilderController::class, 'chatHistories'])->name('settings.agents.history.index');
        Route::get('agents/history/{history}', [AgentBuilderController::class, 'showHistory'])->name('settings.agents.history.show');

        // Knowledge Base (RAG)
        Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
        Route::post('knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
        Route::patch('knowledge/{document}/toggle', [KnowledgeController::class, 'toggleActive'])->name('knowledge.toggle');
        Route::post('knowledge/{document}/re-embed', [KnowledgeController::class, 'reEmbed'])->name('knowledge.re-embed');
        Route::delete('knowledge/{document}', [KnowledgeController::class, 'destroy'])->name('knowledge.destroy');

        Route::resource('productcategories', ProductCategoryController::class);
        // Template Management
        Route::get('templates/create', [PrintTemplateController::class, 'create'])->name('templates.create');
        Route::post('templates', [PrintTemplateController::class, 'store'])->name('templates.store');
        Route::resource('templates', PrintTemplateController::class)->except(['create', 'store']);
        Route::post('templates/assign', [PrintTemplateController::class, 'assign'])->name('templates.assign');
        Route::get('templates/{template}/preview', [PrintTemplateController::class, 'preview'])->name('templates.preview');
        Route::get('templates/{module}/customize', [PrintTemplateController::class, 'customize'])->name('templates.customize');
        Route::post('templates/{module}/customize', [PrintTemplateController::class, 'saveCustomization'])->name('templates.save-customization');
        Route::post('templates/{module}/preview-render', [PrintTemplateController::class, 'renderLivePreview'])->name('templates.preview-render');
        Route::get('default-accounts', [AccountDefaultSettingController::class, 'index'])->name('settings.account-defaults');
        Route::post('default-accounts', [AccountDefaultSettingController::class, 'store'])->name('settings.account-defaults.store');

        // Unified Document Printing Engine
        Route::get('print/{module}/{id}/{action?}', [PrintController::class, 'handle'])
            ->name('print.document');

        Route::get('patrons/dropdown', [PatronController::class, 'dropdown'])->name('patrons.dropdown');
        Route::resource('patrons', PatronController::class);
        Route::post('patrons/batch', [PatronController::class, 'batchStore'])->name('patrons.batchstore');
    });

    // 5. Patrons & Personnel (Membership)
    Route::prefix('membership')->group(function () {
        Route::put('users/mobile-access', [UserMobileAccessController::class, 'update'])->name('users.mobile-access');
        Route::get('users/{user}/whatsapp-verification', [UserController::class, 'whatsappVerificationUrl'])->name('users.whatsapp-verification');
        Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');
        Route::resource('users', UserController::class);
        Route::resource('personnel', PersonnelController::class);

        // HRMS Routes
        Route::resource('departments', DepartmentController::class);
        Route::resource('designations', DesignationController::class);

        Route::resource('shifts', ShiftController::class);
        Route::post('shifts/assign', [ShiftController::class, 'assignShift'])->name('shifts.assign');
        Route::delete('shifts/assign/{employeeShift}', [ShiftController::class, 'removeShiftAssignment'])->name('shifts.unassign');

        Route::resource('attendances', AttendanceController::class);

        Route::resource('leave-applications', LeaveApplicationController::class);
        Route::post('leave-applications/{leaveApplication}/approve', [LeaveApplicationController::class, 'approve'])->name('leave-applications.approve');
        Route::post('leave-balances', [LeaveApplicationController::class, 'storeBalance'])->name('leave-balances.store');
        Route::put('leave-balances/{leaveBalance}', [LeaveApplicationController::class, 'updateBalance'])->name('leave-balances.update');
        Route::delete('leave-balances/{leaveBalance}', [LeaveApplicationController::class, 'destroyBalance'])->name('leave-balances.destroy');

        Route::resource('salary-components', SalaryComponentController::class);
        Route::resource('payroll-periods', PayrollPeriodController::class);
        Route::post('payslips/generate', [PayslipController::class, 'generate'])->name('payslips.generate');
        Route::get('payslips/export-ecr', [PayslipController::class, 'exportEcr'])->name('payslips.export-ecr');
        Route::get('payslips/export-esic', [PayslipController::class, 'exportEsic'])->name('payslips.export-esic');
        Route::resource('payslips', PayslipController::class);
    });

    // 6. Orders & Sales
    Route::prefix('orders')->group(function () {

        Route::resource('quotations', QuotationController::class);
        Route::get('quotations/{quotation}/download', [QuotationController::class, 'downloadPdf'])->name('quotations.download');
        Route::get('quotations/{quotation}/report', [QuotationController::class, 'report'])->name('quotations.report');
        Route::patch('quotations/{quotation}/convert', [QuotationController::class, 'updateConversionStatus'])->name('quotations.convert');
        Route::post('quotations/{quotation}/send-email', [QuotationController::class, 'sendEmail'])->name('quotations.send-email');
        Route::get('customer-po', [CustomerPOController::class, 'index'])->name('customer-po.index');
        Route::post('customer-po', [CustomerPOController::class, 'store'])->name('customer-po.store');
        Route::delete('customer-po/{customerPO}', [CustomerPOController::class, 'destroy'])->name('customer-po.destroy');
        Route::put('customer-po/{customerPO}', [CustomerPOController::class, 'update'])->name('customer-po.update');
        Route::post('customer-po/{customerPO}/convert-salesorder', [CustomerPOController::class, 'convertToSalesOrder'])->name('customer-po.convert-salesorder');
        Route::post('customer-po/{customerPO}/dispatches', [DispatchController::class, 'storeForSalesOrder'])->name('customer-po.dispatches.store');
        Route::resource('salesorders', SalesOrderController::class);
        Route::get('batches/truck-empty-weight', [BatchController::class, 'getTruckEmptyWeight'])->name('batches.truck-empty-weight');
        Route::post('batches/truck-empty-weight', [BatchController::class, 'storeTruckEmptyWeight'])->name('batches.store-truck-empty-weight');
        Route::resource('batches', BatchController::class);
        Route::post('batches/{batch}/send-email', [BatchController::class, 'sendEmail'])->name('batches.send-email');
        Route::get('batches/{batchId}/report', [BatchController::class, 'report'])->name('batches.report');
        Route::get('batches/{batchId}/download', [BatchController::class, 'downloadPdf'])->name('batches.download');
        Route::get('batches/{batch}/token', [BatchController::class, 'token'])->name('batches.token');
        Route::get('batches/{batch}/token/download', [BatchController::class, 'downloadTokenPdf'])->name('batches.token.download');
        Route::get('batches/{batch}/dispatch-token', [BatchController::class, 'dispatchToken'])->name('batches.dispatch-token');
        Route::get('batches/{batch}/dispatch-token/download', [BatchController::class, 'downloadDispatchTokenPdf'])->name('batches.dispatch-token.download');
        Route::get('batches/{batch}/delivery-token', [BatchController::class, 'deliveryToken'])->name('batches.delivery-token');
        Route::get('batches/{batch}/delivery-token/download', [BatchController::class, 'downloadDeliveryTokenPdf'])->name('batches.delivery-token.download');
        Route::get('batches/{batch}/gate-pass', [BatchController::class, 'gatePass'])->name('batches.gate-pass');
        Route::get('batches/{batch}/gate-pass/download', [BatchController::class, 'downloadGatePassPdf'])->name('batches.gate-pass.download');
        Route::post('batches/{batch}/sync', [BatchController::class, 'syncToScheduler'])->name('batches.sync');
        Route::post('batches/ocr', [BatchOcrController::class, 'process'])->name('batches.ocr');
        Route::prefix('api/batch-sheets')->group(function () {
            Route::post('upload', [BatchSheetUploadController::class, 'upload'])->name('batch-sheets.upload');
            Route::get('{id}/status', [BatchSheetUploadController::class, 'status'])->name('batch-sheets.status');
            Route::get('{id}/verify', [BatchSheetUploadController::class, 'verify'])->name('batch-sheets.verify');
            Route::post('{id}/save', [BatchSheetUploadController::class, 'saveToDatabase'])->name('batch-sheets.save');
            Route::post('{id}/template', [BatchSheetUploadController::class, 'saveTemplate'])->name('batch-sheets.save-template');
            Route::post('{id}/suggest-mapping', [BatchSheetUploadController::class, 'suggestMapping'])->name('batch-sheets.suggest-mapping');
            Route::delete('{id}', [BatchSheetUploadController::class, 'destroy'])->name('batch-sheets.destroy');
        });
        Route::post('weighbridge/alert', [WeighbridgeApiController::class, 'sendAlert'])->name('weighbridge.alert');
        Route::get('production/batch', [ProductionApiController::class, 'getConsumption'])->name('batches.production');
        Route::get('dispatches/dropdowns', [DispatchController::class, 'dropdowns'])->name('dispatches.dropdowns');
        Route::post('dispatches/{dispatch}/generate-invoice', [DispatchController::class, 'generateInvoice'])->name('dispatches.generate-invoice');
        Route::delete('dispatches/{dispatch}/delete-invoice', [DispatchController::class, 'deleteInvoice'])->name('dispatches.delete-invoice');
        Route::post('dispatches/{dispatch}/cancel', [DispatchController::class, 'cancel'])->name('dispatches.cancel');
        Route::get('dispatches/{dispatch}/whatsapp-url', [DispatchController::class, 'whatsappUrl'])->name('dispatches.whatsapp-url');
        Route::post('dispatches/{dispatch}/whatsapp-send', [DispatchController::class, 'sendWhatsApp'])->name('dispatches.whatsapp-send');
        Route::resource('dispatches', DispatchController::class);

        Route::resource('partyrates', PartyRateController::class)->except(['create', 'edit', 'show']);
        Route::get('pumprates/resolve', [PumpRateController::class, 'resolve'])->name('pumprates.resolve');
        Route::resource('pumprates', PumpRateController::class)->except(['create', 'edit', 'show']);
    });

    // 7. Inventory & Production
    Route::prefix('inventory')->group(function () {
        Route::get('dashboard', [InventoryDashboardController::class, 'index'])->name('inventory.dashboard');
        Route::patch('adjust/{id}', [InventoryDashboardController::class, 'adjust'])->name('inventory.adjust');
        Route::resource('purchaseorder', PurchaseOrderController::class)->names('purchaseorder');
        Route::get('purchaseorder/{purchase_order}/download/{template?}', [PurchaseOrderController::class, 'downloadPdf'])->name('purchaseorder.download');
        Route::get('purchaseorder/{purchase_order}/report', [PurchaseOrderController::class, 'report'])->name('purchaseorder.report');
        Route::post('purchaseorder/{purchase_order}/generate-bill', [PurchaseOrderController::class, 'generateBill'])->name('purchaseorder.generate-bill');
        Route::delete('purchaseorder/{purchase_order}/delete-bill', [PurchaseOrderController::class, 'deleteBill'])->name('purchaseorder.delete-bill');
        Route::resource('stock-exhausts', StockExhaustController::class);
        Route::get('stocks', [StockController::class, 'index'])->name('stocks.index');
        Route::get('inwards/create/{purchase_order?}', [PurchaseOrderInwardController::class, 'create'])->name('inwards.create');
        Route::resource('inwards', PurchaseOrderInwardController::class)->except(['create']);
        Route::post('inwards/{inward}/update-weight', [PurchaseOrderInwardController::class, 'updateWeight'])->name('inwards.update-weight');
        Route::get('inwards/{inward}/receipt', [PurchaseOrderInwardController::class, 'receipt'])->name('inwards.receipt');
        Route::get('inwards/{inward}/download-receipt', [PurchaseOrderInwardController::class, 'downloadReceipt'])->name('inwards.download-receipt');

        Route::patch('products/{product}/opening-quantity', [ProductController::class, 'setOpeningQuantity'])->name('products.opening-quantity');
        Route::resource('products', ProductController::class);
        Route::post('products/batch', [ProductController::class, 'batchStore'])->name('products.batchstore');
        Route::resource('inventory-audit-logs', InventoryAuditLogController::class);

        Route::get('mixdesigns/gradeingredients/{gradeId}', [MixDesignController::class, 'getGradeIngredients'])->name('mixdesigns.gradeingredients');
        Route::post('mixdesigns/{mixdesign}/toggle-active', [MixDesignController::class, 'toggleActive'])->name('mixdesigns.toggle-active');
        Route::resource('mixdesigns', MixDesignController::class);
        Route::resource('concretegrades', ConcreteGradeController::class);
        Route::get('concrete-quality-tests/lookup/invoices', [ConcreteQualityTestController::class, 'lookupInvoices'])->name('concrete-quality-tests.lookup.invoices');
        Route::resource('concrete-quality-tests', ConcreteQualityTestController::class);
    });

    // 7b. Quality Control (QC Engine & Operational System)
    Route::prefix('quality')->group(function () {
        Route::get('dashboard', [QCDashboardController::class, 'index'])->name('quality.dashboard');

        // Configuration
        Route::prefix('configuration')->group(function () {
            Route::resource('units', QCUnitController::class)->names('quality.config.units');
            Route::post('units/{unit}/toggle', [QCUnitController::class, 'toggleActive'])->name('quality.config.units.toggle');
            Route::resource('test-types', QCTestTypeController::class)->names('quality.config.test-types');
            Route::post('test-types/{test_type}/toggle', [QCTestTypeController::class, 'toggleActive'])->name('quality.config.test-types.toggle');

            Route::resource('test-parameters', QCTestParameterController::class)->names('quality.config.test-parameters');

            Route::get('material-mapping', [QCMaterialTestMappingController::class, 'index'])->name('quality.config.material-mapping.index');
            Route::post('material-mapping', [QCMaterialTestMappingController::class, 'save'])->name('quality.config.material-mapping.save');

            Route::resource('test-schedules', QCTestScheduleController::class)->names('quality.config.test-schedules');
            Route::resource('product-grade-configs', QcProductGradeConfigController::class)->names('quality.config.product-grade-configs');
        });

        // Operations
        Route::resource('samples', QCSampleController::class)->names('quality.samples');

        Route::prefix('tests')->group(function () {
            Route::get('/', [QCTestExecutionController::class, 'index'])->name('quality.tests.index');
            Route::get('pending', [QCTestExecutionController::class, 'pending'])->name('quality.tests.pending');
            Route::get('completed', [QCTestExecutionController::class, 'completed'])->name('quality.tests.completed');
            Route::get('failed', [QCTestExecutionController::class, 'failed'])->name('quality.tests.failed');

            Route::post('/', [QCTestExecutionController::class, 'store'])->name('quality.tests.store');

            Route::delete('{test}', [QCTestExecutionController::class, 'destroy'])->name('quality.tests.destroy');
            Route::get('{test}/execute', [QCTestExecutionController::class, 'executeForm'])->name('quality.tests.execute');
            Route::post('{test}/execute', [QCTestExecutionController::class, 'submitExecution'])->name('quality.tests.submit');

            Route::post('{test}/approve', [QCTestExecutionController::class, 'approve'])->name('quality.tests.approve');
            Route::post('{test}/retest', [QCTestExecutionController::class, 'markRetest'])->name('quality.tests.retest');
            Route::get('{test}/cube-report-pdf', [QCReportController::class, 'downloadCubeReportPdf'])->name('quality.tests.cube-report-pdf');
        });

        // Reports
        Route::get('reports', [QCReportController::class, 'index'])->name('quality.reports.index');
        Route::get('reports/pdf', [QCReportController::class, 'downloadPdf'])->name('quality.reports.pdf');
    });

    // 8. Fleet & Personnel Logistics
    Route::prefix('fleet')->group(function () {
        Route::resource('machines', MachineController::class);
        Route::resource('machinetypes', MachineTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('maintenance-requests', MaintenanceRequestController::class);
        Route::resource('machine-services', MachineServiceController::class);
        Route::resource('machine-trackers', MachineTrackerController::class);

        Route::resource('drivers', DriverController::class);
        Route::resource('fuel-logs', FuelLogController::class);

        // GPS Tracking & Geofences
        Route::resource('gps-devices', GpsDeviceController::class);
        Route::resource('geofences', GeofenceController::class);
        Route::get('gps/live', [GpsTrackingController::class, 'live'])->name('gps.live');
        Route::get('gps/playback', [GpsTrackingController::class, 'playback'])->name('gps.playback');
        Route::get('gps/playback-data', [GpsTrackingController::class, 'playbackData'])->name('gps.playback-data');
    });
    Route::prefix('reports')->group(function () {
        Route::get('bulk-documents', [BulkDocumentReportController::class, 'index'])->name('reports.bulk-documents');
        Route::get('bulk-documents/preview', [BulkDocumentReportController::class, 'documents'])->name('reports.bulk-documents.preview');
        Route::get('bulk-documents/options', [BulkDocumentReportController::class, 'documents'])->name('reports.bulk-documents.options');
        Route::post('bulk-documents/export', [BulkDocumentReportController::class, 'documents'])->name('reports.bulk-documents.export');
        Route::post('bulk-documents/export-zip', [BulkDocumentReportController::class, 'exportZip'])->name('reports.bulk-documents.export-zip');
        // Unified Reports
        Route::get('report', [ReportController::class, 'index'])->name('reports.index');
        Route::get('generate', [ReportController::class, 'generate'])->name('reports.generate');
        Route::get('sales-register', [ReportController::class, 'salesRegister'])->name('reports.sales-register');
        Route::get('purchase-register', [ReportController::class, 'purchaseRegister'])->name('reports.purchase-register');
        Route::get('machine-summary', [ReportController::class, 'machineSummary'])->name('reports.machine-summary');
        Route::get('vehicle-pl', [ReportController::class, 'vehiclePL'])->name('reports.vehicle-pl');
        Route::get('export-status/{key}', [ReportController::class, 'getExportStatus'])->name('reports.export-status');
        Route::get('export-download/{key}', [ReportController::class, 'downloadExport'])->name('reports.export-download');
        Route::get('schedules', [ReportController::class, 'listSchedules'])->name('reports.schedules.index');
        Route::post('schedules', [ReportController::class, 'storeSchedule'])->name('reports.schedules.store');
        Route::delete('schedules/{schedule}', [ReportController::class, 'deleteSchedule'])->name('reports.schedules.destroy');
    });
    // 10. Finance & Accounting
    Route::prefix('finance')->group(function () {
        Route::resource('accounts', AccountsController::class);
        Route::resource('accounttypes', AccountsTypeController::class);
        Route::get('ledgers/dropdown', [LedgerController::class, 'dropdown'])->name('ledgers.dropdown');
        Route::resource('ledgers', LedgerController::class);
        Route::get('ledgers/nextcode', [LedgerController::class, 'getNextCode'])->name('accounting.nextcode');

        Route::resource('vouchertypes', VoucherTypeController::class);
        Route::get('journalentries/generate-voucher-number', [JournalEntryController::class, 'generateVoucherNumber'])->name('journalentries.generate_voucher_number');
        Route::resource('journalentries', JournalEntryController::class);
        Route::prefix('opening-balances')->name('opening-balances.')->controller(OpeningBalanceController::class)->group(function () {
            Route::get('audit-export', 'exportAudit')->name('audit-export');
            Route::get('/', 'index')->name('index');
            Route::put('/', 'save')->name('save');
            Route::delete('/records/{record}', 'remove')->name('remove');
            Route::post('/convert', 'convert')->name('convert');
        });

        Route::resource('expensetypes', ExpenseTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'destroy']);
        Route::resource('discounts', AccountDiscountController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

        Route::resource('pettycash', PettyCashController::class)->except(['create', 'edit', 'show']);
        Route::post('pettycash/{petty_cash}/close', [PettyCashController::class, 'close'])->name('pettycash.close');

        // Bank Reconciliation
        Route::get('reconciliation', [BankReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::post('reconciliation/upload', [BankReconciliationController::class, 'upload'])->name('reconciliation.upload');
        Route::get('reconciliation/lines', [BankReconciliationController::class, 'getLines'])->name('reconciliation.lines');
        Route::post('reconciliation/reconcile', [BankReconciliationController::class, 'reconcile'])->name('reconciliation.reconcile');
        Route::post('reconciliation/unreconcile', [BankReconciliationController::class, 'unreconcile'])->name('reconciliation.unreconcile');
        Route::post('reconciliation/create-voucher', [BankReconciliationController::class, 'createVoucher'])->name('reconciliation.create-voucher');
        Route::get('reconciliation/template', [BankReconciliationController::class, 'downloadTemplate'])->name('reconciliation.template');

        Route::get('payments/next-reference', [PaymentController::class, 'getNextReferenceNumber'])->name('payments.next-reference');
        Route::get('payments/patron-advance-balance', [PaymentController::class, 'getPatronAdvanceBalance'])->name('payments.patron-advance-balance');
        Route::get('payments/{payment}/print', [PaymentController::class, 'printPdf'])->name('payments.print');
        Route::resource('payments', PaymentController::class)->except(['create', 'edit', 'show']);
        Route::get('billings/unbilled-purchase-orders', [BillingController::class, 'getUnbilledPurchaseOrders'])->name('billings.unbilled-pos');
        Route::get('invoices/uninvoiced-dispatches', [InvoiceController::class, 'getUninvoicedDispatches'])->name('invoices.uninvoiced-dispatches');
        Route::get('invoices/check-invoice-number', [InvoiceController::class, 'checkInvoiceNumber'])->name('invoices.check-number');
        Route::get('invoices/next-number', [InvoiceController::class, 'getNextNumber'])->name('invoices.next-number');

        Route::get('invoices/outstanding', [InvoiceController::class, 'outstanding'])->name('invoices.outstanding');
        Route::post('invoices/{invoice}/notes', [InvoiceNoteController::class, 'store'])->name('invoices.notes.store');
        Route::get('crdrnote', [InvoiceNoteController::class, 'index'])->name('crdrnote.index');
        Route::post('crdrnote', [InvoiceNoteController::class, 'moduleStore'])->name('crdrnote.store');
        Route::put('crdrnote/{note}', [InvoiceNoteController::class, 'update'])->name('crdrnote.update');
        Route::delete('crdrnote/{note}', [InvoiceNoteController::class, 'destroy'])->name('crdrnote.destroy');
        Route::get('invoices/{invoice}/print-tax-invoice', [InvoiceController::class, 'printTaxInvoice'])->name('invoices.print-tax-invoice');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->where('invoice', '.*');
        Route::resource('invoices', InvoiceController::class)->except(['create', 'edit', 'destroy']);

        // E-Invoice & E-Way Bill Compliance
        Route::post('invoices/{invoice}/generate-einvoice', [EInvoiceController::class, 'einvoiceGenerate'])->name('invoices.generate-einvoice');
        Route::post('invoices/{invoice}/cancel-einvoice', [EInvoiceController::class, 'cancel'])->name('invoices.cancel-einvoice');
        Route::post('invoices/{invoice}/generate-ewaybill', [EInvoiceController::class, 'generateEWayBill'])->name('invoices.generate-ewaybill');
        Route::post('invoices/{invoice}/cancel-ewaybill', [EInvoiceController::class, 'cancelEWayBill'])->name('invoices.cancel-ewaybill');
        Route::post('batches/{batch}/generate-ewaybill', [EwayBillController::class, 'generateForBatch'])->name('batches.generate-ewaybill');
        Route::post('invoices/{invoice}/generate-standalone-ewaybill', [EwayBillController::class, 'generate'])->name('invoices.generate-standalone-ewaybill');
        Route::post('invoices/{invoice}/cancel-standalone-ewaybill', [EwayBillController::class, 'cancel'])->name('invoices.cancel-standalone-ewaybill');
        Route::get('ewaybills', [EwayBillController::class, 'list'])->name('ewaybills.index');
        Route::get('ewaybills/route-preview', [EwayBillController::class, 'routePreview'])->name('ewaybills.route-preview');
        Route::post('ewaybills/refresh', [EwayBillController::class, 'refreshEWB'])->name('ewaybills.refresh');
        Route::get('ewaybills/{id}/print', [EwayBillController::class, 'print'])->name('ewaybills.print');
        Route::get('invoices/{invoice}/print-ewaybill', [EwayBillController::class, 'printForInvoice'])->name('invoices.print-ewaybill');
        Route::get('batches/{batch}/print-ewaybill', [EwayBillController::class, 'printForBatch'])->name('batches.print-ewaybill');
        Route::post('invoices/{invoice}/setup-demo-compliance', [EInvoiceController::class, 'setupDemoCompliance'])->name('invoices.setup-demo-compliance');
        Route::get('compliance/test', [EInvoiceController::class, 'testPage'])->name('compliance.test');
        Route::post('compliance/test-action', [EInvoiceController::class, 'testAction'])->name('compliance.test-action');

        Route::delete('billings/{billing}', [BillingController::class, 'destroy'])->name('billings.destroy')->where('billing', '.*');
        Route::resource('billings', BillingController::class)->except(['create', 'edit', 'destroy']);

        // Bank Reconciliation (BRS)
        Route::prefix('reconciliation')->group(function () {
            Route::get('/', [BankReconciliationController::class, 'index'])->name('reconciliation.index');
            Route::post('/upload', [BankReconciliationController::class, 'upload'])->name('reconciliation.upload');
            Route::get('/lines', [BankReconciliationController::class, 'getLines'])->name('reconciliation.lines');
            Route::post('/reconcile', [BankReconciliationController::class, 'reconcile'])->name('reconciliation.reconcile');
            Route::post('/unreconcile', [BankReconciliationController::class, 'unreconcile'])->name('reconciliation.unreconcile');
            Route::post('/create-voucher', [BankReconciliationController::class, 'createVoucher'])->name('reconciliation.create-voucher');
        });

        // Public Document Share Token Generation (Authenticated)
        Route::post('invoices/{id}/share', [InvoiceShareController::class, 'generateLink'])->name('invoices.share');
        Route::post('reports/share', [InvoiceShareController::class, 'generateLink'])->name('reports.share');
        Route::post('batches/{id}/share', [InvoiceShareController::class, 'generateLink'])->name('batches.share');
    });

    // Bridge Proxy (Bypass CORS for local hardware)
    Route::get('/bridge/weight', function (Request $request) {
        $plantId = session('active_plant_id');
        $customSetting = $plantId ? CustomSetting::where('plant_id', $plantId)->where('module_name', 'batching')->first() : null;
        $mode = (int) ($customSetting?->settings['new_weight'] ?? $customSetting?->settings['newweight'] ?? 1);

        $primaryUrl = $mode === 2 ? 'https://localhost:8074/api/port' : 'http://localhost:8089/api/port';
        $fallbackUrl = $mode === 2 ? 'http://localhost:8089/api/port' : 'https://localhost:8074/api/port';

        try {
            return Http::withoutVerifying()->timeout(3)->get($primaryUrl)->body();
        } catch (Throwable $e) {
            try {
                return Http::withoutVerifying()->timeout(3)->get($fallbackUrl)->body();
            } catch (Throwable $fallbackEx) {
                return response('Bridge connection failed', 503);
            }
        }
    })->name('bridge.weight');
});

// Public Document Share Access (Guest/No Authentication)
Route::get('public/invoice/{token}', [InvoiceShareController::class, 'viewInvoice'])->name('public.invoice.view');
Route::get('share/invoice/{token}', [InvoiceShareController::class, 'viewInvoice'])->name('share.invoice.view');
Route::get('public/invoice/{token}/pdf', [InvoiceShareController::class, 'downloadPDF'])->name('public.invoice.pdf');
Route::get('share/invoice/{token}/pdf', [InvoiceShareController::class, 'downloadPDF'])->name('share.invoice.pdf');

Route::get('public/report/{token}', [InvoiceShareController::class, 'viewReport'])->name('public.report.view');
Route::get('share/report/{token}', [InvoiceShareController::class, 'viewReport'])->name('share.report.view');
Route::get('public/report/{token}/pdf', [InvoiceShareController::class, 'downloadReportPDF'])->name('public.report.pdf');
Route::get('share/report/{token}/pdf', [InvoiceShareController::class, 'downloadReportPDF'])->name('share.report.pdf');

Route::get('public/batch/{token}', [InvoiceShareController::class, 'viewBatch'])->name('public.batch.view');
Route::get('share/batch/{token}', [InvoiceShareController::class, 'viewBatch'])->name('share.batch.view');
Route::get('public/batch/{token}/pdf', [InvoiceShareController::class, 'downloadBatchPDF'])->name('public.batch.pdf');
Route::get('share/batch/{token}/pdf', [InvoiceShareController::class, 'downloadBatchPDF'])->name('share.batch.pdf');

// Public Gate Pass Verification (Guest)
Route::get('/public/gatepass/verify/{batch}/{hash}', [BatchController::class, 'publicVerifyGatePass'])->name('public.gatepass.verify');
Route::post('/public/gatepass/verify/{batch}/{hash}', [BatchController::class, 'publicConfirmGatePass'])->name('public.gatepass.confirm');

// Direct Storage Route for serving uploaded files reliably (WAMP/Windows junctions & symlinks)
Route::get('storage/{path}', function (string $path) {
    $storagePath = storage_path('app/public/'.$path);
    if (file_exists($storagePath) && is_file($storagePath)) {
        return response()->file($storagePath);
    }

    $pubPath = public_path('storage/'.$path);
    if (file_exists($pubPath) && is_file($pubPath)) {
        return response()->file($pubPath);
    }

    abort(404);
})->where('path', '.*')->name('storage.file');

require __DIR__.'/auth.php';
