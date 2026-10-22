<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quotations, supplier payables and expenses. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('open'); // open, accepted, rejected, converted
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        // What we owe suppliers. Usually created from a goods received note.
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_invoice_no', 50)->nullable();
            $table->date('bill_date');
            $table->date('due_date');
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('unpaid'); // unpaid, partial, paid, cancelled
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->string('method', 20);
            $table->decimal('amount', 14, 2);
            $table->string('reference', 100)->nullable();
            $table->string('cheque_number', 50)->nullable();
            $table->date('cheque_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_bill_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 14, 2);
            $table->string('method', 20); // cash, bank_transfer, cheque, card
            $table->string('payee')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('description')->nullable();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_bills');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
