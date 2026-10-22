<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** POS, cashier shifts, refunds and the cheque register. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('open'); // open, closed
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_float', 14, 2)->default(0);
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('counted_cash', 14, 2)->nullable();
            $table->decimal('variance', 14, 2)->nullable();
            $table->json('denominations')->nullable();
            $table->text('closing_notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // Cash paid into or out of the drawer that is not a sale (petty cash, bank drop, float top-up).
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // in, out
            $table->decimal('amount', 14, 2);
            $table->string('reason');
            $table->foreignId('expense_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pos_held_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->json('cart');
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('source', 10)->default('office')->after('status'); // office, pos
            $table->foreignId('cashier_shift_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $table->decimal('cash_tendered', 14, 2)->nullable()->after('credited_amount');
            $table->decimal('change_given', 14, 2)->nullable()->after('cash_tendered');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            // Cheque register: received (in hand) -> deposited -> cleared, or returned.
            $table->date('deposited_date')->nullable()->after('cheque_date');
            $table->string('deposit_bank', 100)->nullable()->after('deposited_date');
            $table->date('cleared_date')->nullable()->after('deposit_bank');
        });

        // Money given back to a customer (counter return, overpayment refund).
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('refund_date');
            $table->string('method', 20);
            $table->decimal('amount', 14, 2);
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_shift_id');
            $table->dropColumn(['deposited_date', 'deposit_bank', 'cleared_date']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_shift_id');
            $table->dropColumn(['source', 'cash_tendered', 'change_given']);
        });
        Schema::dropIfExists('pos_held_sales');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cashier_shifts');
    }
};
