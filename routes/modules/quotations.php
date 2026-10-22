<?php

// Routes for the quotations module (included from routes/web.php inside its role group).

use App\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

Route::resource('quotations', QuotationController::class)->except(['destroy']);
Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
Route::post('/quotations/{quotation}/status', [QuotationController::class, 'status'])->name('quotations.status');
Route::post('/quotations/{quotation}/convert-order', [QuotationController::class, 'convertToOrder'])->name('quotations.convert-order');
Route::post('/quotations/{quotation}/convert-invoice', [QuotationController::class, 'convertToInvoice'])->name('quotations.convert-invoice');
