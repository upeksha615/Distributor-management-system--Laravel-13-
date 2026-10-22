<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CompanyBankAccountController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentPdfController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentInboxController;
use App\Http\Controllers\PaymentVerificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RepController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
| Back office. Every group below is guarded by a permission key (see App\Support\Permissions);
| roles and their permissions are edited in Setup → Roles & permissions.
*/
Route::middleware(['auth', 'area:staff'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // ---- Products & stock ----
    Route::middleware('can:products.view')->group(function () {
        Route::get('/products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/stock', [StockController::class, 'index'])->middleware('can:stock.view_qty')->name('stock.index');
        Route::get('/stock/movements', [StockController::class, 'movements'])->middleware('can:stock.view_qty')->name('stock.movements');
    });
    Route::middleware('can:products.manage')->group(function () {
        Route::resource('products', ProductController::class)->only(['create', 'store', 'edit', 'update']);
        Route::post('/products/{product}/part-numbers', [ProductController::class, 'addPartNumber'])->name('products.part-numbers.store');
        Route::delete('/products/{product}/part-numbers/{partNumber}', [ProductController::class, 'deletePartNumber'])->name('products.part-numbers.destroy');
        Route::post('/products/{product}/fitments', [ProductController::class, 'addFitment'])->name('products.fitments.store');
        Route::delete('/products/{product}/fitments/{fitment}', [ProductController::class, 'deleteFitment'])->name('products.fitments.destroy');
        Route::get('/labels', [ProductController::class, 'labelsForm'])->name('labels.index');
        Route::post('/labels', [ProductController::class, 'labelsPrint'])->name('labels.print');
        Route::get('/lookups/{type}', [LookupController::class, 'index'])->whereIn('type', ['categories', 'brands'])->name('lookups.index');
        Route::post('/lookups/{type}', [LookupController::class, 'store'])->whereIn('type', ['categories', 'brands'])->name('lookups.store');
        Route::put('/lookups/{type}/{id}', [LookupController::class, 'update'])->whereIn('type', ['categories', 'brands'])->name('lookups.update');
        Route::delete('/lookups/{type}/{id}', [LookupController::class, 'destroy'])->whereIn('type', ['categories', 'brands'])->name('lookups.destroy');
    });
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('can:products.view')->name('products.show');
    Route::get('/products/{product}/image', [ProductController::class, 'image'])->middleware('can:products.view')->name('products.image');

    // Upcoming shipments: pending quantities (not stock) shown to reps; stock changes only on receipt.
    Route::middleware('can:shipments.manage')->group(function () {
        Route::get('/shipments/upload', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::get('/shipments/template', [ShipmentController::class, 'template'])->name('shipments.template');
        Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
        Route::post('/shipments/{shipment}/receive', [ShipmentController::class, 'receive'])->name('shipments.receive');
        Route::post('/shipments/{shipment}/cancel', [ShipmentController::class, 'cancel'])->name('shipments.cancel');
    });
    Route::middleware('can:shipments.view')->group(function () {
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->whereNumber('shipment')->name('shipments.show');
    });
    Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update'])->middleware('can:warehouses.manage');
    Route::middleware('can:stock.adjust')->group(function () {
        Route::get('/stock/adjust', [StockController::class, 'adjustForm'])->name('stock.adjust');
        Route::post('/stock/adjust', [StockController::class, 'adjust']);
    });
    Route::middleware('can:stock.transfer')->group(function () {
        Route::get('/stock/transfer', [StockController::class, 'transferForm'])->name('stock.transfer');
        Route::post('/stock/transfer', [StockController::class, 'transfer']);
    });
    // Imports: each type checks its own permission in ImportController.
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('/imports/{type}/template', [ImportController::class, 'template'])->name('imports.template');
    Route::post('/imports/{type}', [ImportController::class, 'store'])->name('imports.store');

    // ---- Purchasing ----
    Route::middleware('can:suppliers.view')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    });
    Route::middleware('can:suppliers.manage')->group(function () {
        Route::resource('suppliers', SupplierController::class)->only(['create', 'store', 'edit', 'update']);
    });
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('can:suppliers.view')->name('suppliers.show');
    Route::middleware('can:purchases.manage')->group(function () {
        Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])->parameters(['purchase-orders' => 'purchaseOrder']);
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
        Route::get('/purchase-orders/{purchaseOrder}/pdf', [DocumentPdfController::class, 'purchaseOrder'])->name('purchase-orders.pdf');
    });
    Route::post('/purchase-orders/{purchaseOrder}/order', [PurchaseOrderController::class, 'markOrdered'])->middleware('can:purchases.approve')->name('purchase-orders.order');
    Route::middleware('can:purchases.receive')->group(function () {
        Route::get('/purchase-orders/{purchaseOrder}/receive', [GoodsReceiptController::class, 'create'])->name('goods-receipts.create');
        Route::post('/purchase-orders/{purchaseOrder}/receive', [GoodsReceiptController::class, 'store'])->name('goods-receipts.store');
        Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('goods-receipts.show');
        Route::get('/goods-receipts/{goodsReceipt}/pdf', [DocumentPdfController::class, 'goodsReceipt'])->name('goods-receipts.pdf');
    });
    Route::middleware('can:payables.view')->group(fn () => require __DIR__.'/modules/payables.php');

    // ---- Dealers & sales ----
    Route::middleware('can:customers.manage')->group(function () {
        Route::resource('customers', CustomerController::class)->only(['create', 'store', 'edit', 'update']);
    });
    Route::middleware('can:customers.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
        Route::get('/customers/{customer}/open-invoices', [PaymentController::class, 'openInvoices'])->name('payments.open-invoices');
    });
    Route::middleware('can:quotations.manage')->group(fn () => require __DIR__.'/modules/quotations.php');
    Route::middleware('can:sales_orders.manage')->group(function () {
        Route::resource('sales-orders', SalesOrderController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])->parameters(['sales-orders' => 'salesOrder']);
        Route::post('/sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->name('sales-orders.cancel');
        Route::get('/sales-orders/{salesOrder}/pdf', [DocumentPdfController::class, 'salesOrder'])->name('sales-orders.pdf');
    });
    Route::middleware('can:invoices.create')->group(function () {
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/sales-orders/{salesOrder}/invoice', [SalesOrderController::class, 'invoice'])->name('sales-orders.invoice');
    });
    Route::middleware('can:invoices.view')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf', [DocumentPdfController::class, 'invoice'])->name('invoices.pdf');
        Route::get('/reports/aged-debtors', [ReportController::class, 'agedDebtors'])->name('reports.aged-debtors');
    });
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('can:invoices.cancel')->name('invoices.cancel');
    Route::post('/invoices/{invoice}/email', \App\Http\Controllers\InvoiceEmailController::class)->middleware(['can:invoices.email', 'throttle:20,1'])->name('invoices.email');

    // ---- Credit notes ----
    Route::middleware('can:credit_notes.view')->group(function () {
        Route::get('/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
        Route::get('/credit-notes/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
        Route::get('/credit-notes/{creditNote}/pdf', [DocumentPdfController::class, 'creditNote'])->name('credit-notes.pdf');
    });
    Route::middleware('can:credit_notes.request')->group(function () {
        Route::get('/invoices/{invoice}/credit-note', [CreditNoteController::class, 'create'])->name('credit-notes.create');
        Route::post('/invoices/{invoice}/credit-note', [CreditNoteController::class, 'store'])->name('credit-notes.store');
    });

    // ---- Dealer payments ----
    Route::middleware('can:payments.view')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
        Route::get('/payments/{payment}/pdf', [DocumentPdfController::class, 'payment'])->name('payments.pdf');
    });
    Route::middleware('can:payments.record')->group(function () {
        Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    });
    // Allocation / actions check their own permissions (record before verification, allocate / verify / reverse after).
    Route::post('/payments/{payment}/allocate', [PaymentController::class, 'allocate'])->name('payments.allocate');
    Route::post('/payments/{payment}/reallocate', [PaymentController::class, 'reallocate'])->name('payments.reallocate');
    Route::post('/payments/{payment}/attachments', [PaymentController::class, 'uploadAttachment'])->name('payments.attachments.store');
    Route::post('/payments/{payment}/{action}', [PaymentController::class, 'action'])
        ->whereIn('action', ['submit', 'verify', 'reject', 'reject-duplicate', 'request-info', 'reverse', 'not-duplicate'])->name('payments.action');
    Route::get('/attachments/{attachment}', [PaymentController::class, 'attachment'])->middleware('can:payments.view')->name('attachments.show');

    // Payment slip inbox (Office Admin) and verification queue (Accountant).
    Route::middleware('can:payments.record')->group(function () {
        Route::get('/payment-inbox', [PaymentInboxController::class, 'index'])->name('payment-inbox.index');
        Route::get('/payment-inbox/upload', [PaymentInboxController::class, 'create'])->name('payment-inbox.create');
        Route::post('/payment-inbox', [PaymentInboxController::class, 'store'])->name('payment-inbox.store');
        Route::get('/payment-inbox/{submission}', [PaymentInboxController::class, 'show'])->name('payment-inbox.show');
        Route::post('/payment-inbox/{submission}/match', [PaymentInboxController::class, 'match'])->name('payment-inbox.match');
        Route::post('/payment-inbox/{submission}/convert', [PaymentInboxController::class, 'convert'])->name('payment-inbox.convert');
        Route::post('/payment-inbox/{submission}/{action}', [PaymentInboxController::class, 'action'])->whereIn('action', ['request-info', 'reject', 'duplicate', 'assign'])->name('payment-inbox.action');
    });
    Route::get('/payment-verification', [PaymentVerificationController::class, 'index'])->middleware('can:payments.verify')->name('payment-verification.index');

    // ---- Field sales ----
    Route::middleware('can:field.rep')->group(function () {
        Route::get('/rep', [RepController::class, 'index'])->name('rep.index');
        Route::get('/rep/history', [RepController::class, 'history'])->name('rep.history');
        Route::post('/rep/visits', [RepController::class, 'checkIn'])->name('rep.visits.store');
        Route::get('/rep/visits/{visit}/order', [RepController::class, 'orderForm'])->name('rep.visits.order');
        Route::post('/rep/visits/{visit}/order', [RepController::class, 'orderStore']);
        Route::post('/rep/visits/{visit}/payment', [RepController::class, 'paymentStore'])->name('rep.visits.payment');
        Route::post('/rep/visits/{visit}/checkout', [RepController::class, 'checkOut'])->name('rep.visits.checkout');
    });
    // Visit page: the rep's own visits, or anyone who monitors the field (checked in the controller).
    Route::get('/rep/visits/{visit}', [RepController::class, 'show'])->name('rep.visits.show');
    Route::middleware('can:field.monitor')->group(function () {
        Route::get('/field', [FieldController::class, 'index'])->name('field.index');
        Route::get('/field/live', [FieldController::class, 'live'])->name('field.live');
        Route::post('/customers/{customer}/reset-location', [RepController::class, 'resetLocation'])->name('customers.reset-location');
    });

    // ---- Accounts, expenses, cheques (route-level permissions inside the module file) ----
    require __DIR__.'/modules/expenses.php';

    // ---- Reports ----
    Route::middleware('can:reports.finance')->group(function () {
        Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
        Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
    });
    Route::middleware('can:reports.stock')->group(function () {
        Route::get('/reports/stock-valuation', [ReportController::class, 'stockValuation'])->name('reports.stock-valuation');
        Route::get('/reports/low-stock', [ReportController::class, 'lowStock'])->name('reports.low-stock');
    });

    // ---- Banks ----
    Route::get('/banks/{bank}/branch-lookup', [BankController::class, 'branchLookup'])->name('banks.branch-lookup');
    Route::middleware('can:banks.manage')->group(function () {
        Route::get('/banks', [BankController::class, 'index'])->name('banks.index');
        Route::post('/banks', [BankController::class, 'store'])->name('banks.store');
        Route::put('/banks/{bank}', [BankController::class, 'update'])->name('banks.update');
        Route::get('/banks/{bank}/branches', [BankController::class, 'branches'])->name('banks.branches');
        Route::post('/banks/{bank}/branches', [BankController::class, 'storeBranch'])->name('banks.branches.store');
        Route::put('/banks/{bank}/branches/{branch}', [BankController::class, 'updateBranch'])->name('banks.branches.update');
        Route::post('/banks/{bank}/branches/import', [BankController::class, 'importBranches'])->name('banks.branches.import');
        Route::resource('bank-accounts', CompanyBankAccountController::class)->except(['show', 'destroy'])->parameters(['bank-accounts' => 'bankAccount']);
    });

    // ---- Administration ----
    Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:users.manage');
    Route::post('/users/{user}/send-reset', [\App\Http\Controllers\PasswordResetController::class, 'sendForUser'])->middleware(['can:users.manage', 'throttle:10,1'])->name('users.send-reset');
    Route::middleware('can:system.manage')->prefix('system')->name('system.')->controller(\App\Http\Controllers\SystemController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/test-email', 'testEmail')->middleware('throttle:5,1')->name('test-email');
        Route::get('/emails', 'emails')->name('emails');
    });
    Route::middleware('can:system.manage')->prefix('system/mail')->name('system.mail')->controller(\App\Http\Controllers\MailSettingsController::class)->group(function () {
        Route::get('/', 'edit')->name('');
        Route::put('/', 'update')->name('.update');
        Route::post('/test', 'test')->middleware('throttle:10,1')->name('.test');
        Route::post('/reset', 'reset')->name('.reset');
    });
    Route::resource('roles', RoleController::class)->except(['show'])->middleware('can:roles.manage');
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
    Route::middleware('can:notices.manage')->controller(\App\Http\Controllers\NoticeController::class)->group(function () {
        Route::get('/notices', 'index')->name('notices.index');
        Route::post('/notices', 'store')->name('notices.store');
        Route::put('/notices/{notice}', 'update')->name('notices.update');
        Route::delete('/notices/{notice}', 'destroy')->name('notices.destroy');
        Route::get('/holidays', 'holidays')->name('holidays.index');
        Route::post('/holidays', 'storeHoliday')->name('holidays.store');
        Route::delete('/holidays/{holiday}', 'destroyHoliday')->name('holidays.destroy');
    });
    Route::get('/audit-log', [SettingsController::class, 'audit'])->middleware('can:audit.view')->name('audit.index');
});
