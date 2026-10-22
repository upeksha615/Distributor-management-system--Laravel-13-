<?php

// Routes for the payables module (included from routes/web.php inside the payables.view permission group).

use App\Http\Controllers\SupplierBillController;
use App\Http\Controllers\SupplierPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/supplier-bills', [SupplierBillController::class, 'index'])->name('supplier-bills.index');
Route::get('/supplier-bills/create', [SupplierBillController::class, 'create'])->name('supplier-bills.create')->middleware('can:payables.manage');
Route::post('/supplier-bills', [SupplierBillController::class, 'store'])->name('supplier-bills.store')->middleware('can:payables.manage');
Route::get('/supplier-bills/{supplierBill}', [SupplierBillController::class, 'show'])->name('supplier-bills.show');
Route::post('/supplier-bills/{supplierBill}/cancel', [SupplierBillController::class, 'cancel'])->name('supplier-bills.cancel')->middleware('can:payables.manage');

Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
Route::get('/supplier-payments/create', [SupplierPaymentController::class, 'create'])->name('supplier-payments.create')->middleware('can:payables.manage');
Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store')->middleware('can:payables.manage');
Route::get('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show'])->name('supplier-payments.show');
Route::post('/supplier-payments/{supplierPayment}/allocate', [SupplierPaymentController::class, 'allocate'])->name('supplier-payments.allocate')->middleware('can:payables.manage');
Route::get('/supplier-payments/{supplierPayment}/pdf', [SupplierPaymentController::class, 'pdf'])->name('supplier-payments.pdf');
Route::get('/suppliers/{supplier}/open-bills', [SupplierPaymentController::class, 'openBills'])->name('supplier-payments.open-bills');

Route::get('/suppliers/{supplier}/statement', [SupplierBillController::class, 'statement'])->name('suppliers.statement');
Route::get('/reports/aged-creditors', [SupplierBillController::class, 'agedCreditors'])->name('reports.aged-creditors');
