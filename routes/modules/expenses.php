<?php

// Routes for expenses and the cheque register (each group checks its own permission).

use App\Http\Controllers\ChequeController;
use App\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:expenses.manage')->group(function () {
Route::resource('expenses', ExpenseController::class)->except(['show']);

// Expense categories: simple name list.
Route::get('/expense-categories', [ExpenseController::class, 'categories'])->name('expense-categories.index');
Route::post('/expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
Route::put('/expense-categories/{category}', [ExpenseController::class, 'updateCategory'])->name('expense-categories.update');
Route::delete('/expense-categories/{category}', [ExpenseController::class, 'destroyCategory'])->name('expense-categories.destroy');

});
Route::get('/reports/expenses', [ExpenseController::class, 'report'])->middleware('can:reports.finance')->name('reports.expenses');

// Cheque register: customer cheques received (payments with method = cheque).
Route::get('/cheques', [ChequeController::class, 'index'])->middleware('can:payments.view')->name('cheques.index');
Route::post('/cheques/deposit', [ChequeController::class, 'deposit'])->middleware('can:cheques.manage')->name('cheques.deposit');
Route::post('/cheques/{payment}/clear', [ChequeController::class, 'clear'])->middleware('can:cheques.clear')->name('cheques.clear');
Route::post('/cheques/{payment}/return', [ChequeController::class, 'returnCheque'])->middleware('can:cheques.clear')->name('cheques.return');
