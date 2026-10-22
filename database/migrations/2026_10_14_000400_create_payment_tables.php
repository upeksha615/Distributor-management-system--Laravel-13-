<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->string('method', 20); // cash, cheque, bank_transfer, card
            $table->decimal('amount', 14, 2);
            $table->string('reference', 100)->nullable();
            $table->string('cheque_number', 50)->nullable();
            $table->string('cheque_bank', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('status', 20)->default('received'); // received, returned
            $table->date('returned_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
