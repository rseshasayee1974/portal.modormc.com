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

// OTP Verification — requires auth but NOT full session clearance
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/verifyotp', [\App\Http\Controllers\OtpController::class, 'show'])->name('otp.show');
    Route::post('/verifyotp', [\App\Http\Controllers\OtpController::class, 'verify'])->name('otp.verify');
    Route::post('/resendotp', [\App\Http\Controllers\OtpController::class, 'resend'])->name('otp.resend');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // 0. Dashboard
    Route::get('/session-ping', function () {
        return response()->json(['status' => 'active']);
    })->name('session.ping');

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

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

    // 1. Context (Entity/Plant Switcher)
    Route::prefix('context')->group(function () {
        Route::get('/selectentity', [\App\Http\Controllers\EntityContextController::class, 'index'])->name('entity-context.index');
        Route::post('/selectentity', [\App\Http\Controllers\EntityContextController::class, 'store'])->name('entity-context.store');
        Route::post('/selectplant', [\App\Http\Controllers\EntityContextController::class, 'setPlant'])->name('entity-context.set-plant');
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
        Route::resource('statecodes', \App\Http\Controllers\StateCodeController::class)->except(['create', 'edit', 'show']);
        Route::resource('subscriptionstatuses', \App\Http\Controllers\SubscriptionStatusController::class)->except(['create', 'edit', 'show']);
        Route::resource('taxes', \App\Http\Controllers\TaxController::class);
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
        
    });

    // 4. Settings & Permissions
    Route::prefix('settings')->group(function () {
        Route::resource('termsconditions', \App\Http\Controllers\TermsConditionController::class);
        Route::resource('permissions', \App\Http\Controllers\PermissionController::class);
        Route::resource('roles', \App\Http\Controllers\RoleController::class);
        Route::resource('menus', \App\Http\Controllers\MenuController::class);
        Route::resource('sites', \App\Http\Controllers\SiteController::class);
        
        // Template Management
        Route::resource('templates', \App\Http\Controllers\PrintTemplateController::class);
        Route::post('templates/assign', [\App\Http\Controllers\PrintTemplateController::class, 'assign'])->name('templates.assign');
        Route::get('templates/{template}/preview', [\App\Http\Controllers\PrintTemplateController::class, 'preview'])->name('templates.preview');
        Route::get('templates/{module}/customize', [\App\Http\Controllers\PrintTemplateController::class, 'customize'])->name('templates.customize');
        Route::post('templates/{module}/customize', [\App\Http\Controllers\PrintTemplateController::class, 'saveCustomization'])->name('templates.save-customization');

        // Unified Document Printing Engine
        Route::get('print/{module}/{id}/{action?}', [\App\Http\Controllers\PrintController::class, 'handle'])
            ->name('print.document');
            
        Route::resource('patrons', \App\Http\Controllers\PatronController::class);
        Route::post('patrons/batch', [\App\Http\Controllers\PatronController::class, 'batchStore'])->name('patrons.batchstore');
    });

    // 5. Patrons & Personnel (Membership)
    Route::prefix('membership')->group(function () {
        Route::resource('users', \App\Http\Controllers\UserController::class);
        Route::resource('personnel', \App\Http\Controllers\PersonnelController::class);
    });

    // 6. Orders & Sales
    Route::prefix('orders')->group(function () {
        Route::resource('purchaseorder', PurchaseOrderController::class)->names('purchaseorder');
        Route::get('purchaseorder/{purchase_order}/download/{template?}', [PurchaseOrderController::class, 'downloadPdf'])->name('purchaseorder.download');
        Route::get('purchaseorder/{purchase_order}/report', [PurchaseOrderController::class, 'report'])->name('purchaseorder.report');
        Route::post('purchaseorder/{purchase_order}/generate-bill', [PurchaseOrderController::class, 'generateBill'])->name('purchaseorder.generate-bill');
        
        Route::resource('quotations', \App\Http\Controllers\QuotationController::class);
        Route::get('quotations/{quotation}/download', [\App\Http\Controllers\QuotationController::class, 'downloadPdf'])->name('quotations.download');
        Route::get('quotations/{quotation}/report', [\App\Http\Controllers\QuotationController::class, 'report'])->name('quotations.report');
        Route::patch('quotations/{quotation}/convert', [\App\Http\Controllers\QuotationController::class, 'updateConversionStatus'])->name('quotations.convert');
        Route::post('salesorders', [\App\Http\Controllers\SalesOrderController::class, 'store'])->name('salesorders.store');
        Route::post('salesorders/{salesOrder}/dispatches', [\App\Http\Controllers\DispatchController::class, 'storeForSalesOrder'])->name('salesorders.dispatches.store');
        Route::resource('workorders', \App\Http\Controllers\WorkOrderController::class);
        Route::resource('batches', \App\Http\Controllers\BatchController::class)->except(['create', 'show', 'edit']);
        Route::get('batches/{batch}/report', [\App\Http\Controllers\BatchController::class, 'report'])->name('batches.report');
        Route::get('batches/{batch}/download', [\App\Http\Controllers\BatchController::class, 'downloadPdf'])->name('batches.download');
        Route::get('production/batch', [\App\Http\Controllers\Api\ProductionApiController::class, 'getConsumption'])->name('batches.production');
        Route::resource('dispatches', \App\Http\Controllers\DispatchController::class)->except(['create', 'show', 'edit']);
        
        Route::resource('partyrates', \App\Http\Controllers\PartyRateController::class)->except(['create', 'edit', 'show']);
    });

    // 7. Inventory & Production
    Route::prefix('inventory')->group(function () {
        Route::get('stocks', [\App\Http\Controllers\StockController::class, 'index'])->name('stocks.index');
        Route::resource('inwards', PurchaseOrderInwardController::class);
        Route::get('inwards/create/{purchase_order?}', [PurchaseOrderInwardController::class, 'create'])->name('inwards.create');
        Route::post('inwards/{inward}/update-weight', [PurchaseOrderInwardController::class, 'updateWeight'])->name('inwards.update-weight');
        Route::resource('productunits', \App\Http\Controllers\ProductUnitController::class);
        Route::resource('productcategories', \App\Http\Controllers\ProductCategoryController::class);
        Route::resource('products', \App\Http\Controllers\ProductController::class);
        Route::post('products/batch', [\App\Http\Controllers\ProductController::class, 'batchStore'])->name('products.batchstore');
        
        Route::get('mixdesigns/gradeingredients/{gradeId}', [\App\Http\Controllers\MixDesignController::class, 'getGradeIngredients'])->name('mixdesigns.gradeingredients');
        Route::resource('mixdesigns', \App\Http\Controllers\MixDesignController::class);
        Route::resource('concretegrades', \App\Http\Controllers\ConcreteGradeController::class);
    });

    // 8. Fleet & Personnel Logistics
    Route::prefix('fleet')->group(function () {
        Route::resource('machines', \App\Http\Controllers\MachineController::class);
        Route::resource('machinetypes', \App\Http\Controllers\MachineTypeController::class)->except(['create', 'edit', 'show']);
    });

    // 9. Trips & Operations
    Route::prefix('rmc')->group(function () {
        Route::get('trips/dashboard', [\App\Http\Controllers\TripController::class, 'dashboard'])->name('trips.dashboard');
        Route::post('trips/{trip}/payments', [\App\Http\Controllers\TripController::class, 'recordPayment'])->name('trips.recordpayment');
        Route::resource('trips', \App\Http\Controllers\TripController::class);
    });

    // 10. Finance & Accounting
    Route::prefix('finance')->group(function () {
        Route::resource('accounts', \App\Http\Controllers\AccountsController::class);
        Route::resource('accounttypes', \App\Http\Controllers\AccountsTypeController::class);
        Route::resource('ledgers', \App\Http\Controllers\LedgerController::class);
        Route::get('ledgers/nextcode', [\App\Http\Controllers\LedgerController::class, 'getNextCode'])->name('accounting.nextcode');
        
        Route::resource('vouchertypes', \App\Http\Controllers\VoucherTypeController::class);
        Route::resource('journalentries', \App\Http\Controllers\JournalEntryController::class);
        
        Route::resource('expensetypes', \App\Http\Controllers\ExpenseTypeController::class)->except(['create', 'edit', 'show']);
        Route::resource('expenses', \App\Http\Controllers\ExpenseController::class)->only(['index', 'store', 'destroy']);
        
        Route::resource('pettycash', \App\Http\Controllers\PettyCashController::class)->except(['create', 'edit', 'show']);
        Route::post('pettycash/{petty_cash}/close', [\App\Http\Controllers\PettyCashController::class, 'close'])->name('pettycash.close');
        
        Route::resource('payments', \App\Http\Controllers\PaymentController::class)->except(['create', 'edit', 'show']);
        Route::resource('invoices', \App\Http\Controllers\InvoiceController::class)->except(['create', 'edit']);
    });

    // Bridge Proxy (Bypass CORS for local hardware)
    Route::get('/bridge/weight', function () {
        try {
            return \Illuminate\Support\Facades\Http::timeout(3)->get('http://localhost:8089/api/port')->body();
        } catch (\Exception $e) {
            return response('Bridge connection failed', 503);
        }
    })->name('bridge.weight');
});
