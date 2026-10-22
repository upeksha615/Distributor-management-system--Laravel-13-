<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The POS / cashier section was removed: this business sells through the office and reps.
 * Former POS sales stay as normal invoices; only the shift links and POS-only tables go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_shift_id');
            $table->dropColumn(['source', 'cash_tendered', 'change_given']);
        });
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('cashier_shift_id'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('cashier_shift_id'));

        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('pos_held_sales');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cashier_shifts');

        DB::table('settings')->whereIn('key', ['pos_allow_price_edit', 'receipt_footer'])->delete();
    }

    public function down(): void
    {
        // Not reversible: re-run the 2026_10_15_000200 migration's tables manually if POS is ever needed again.
    }
};
