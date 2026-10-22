<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Field sales: reps check in at dealers, take orders and collect payments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rep_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_in_accuracy')->nullable(); // metres reported by the phone
            $table->unsignedInteger('distance_m')->nullable(); // from the dealer's saved location
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->string('outcome', 20)->nullable(); // order, payment, order_payment, no_order, follow_up, closed
            $table->text('notes')->nullable();
            $table->date('next_visit_date')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'checked_out_at']);
            $table->index('checked_in_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('source', 10)->default('office')->after('status'); // office, rep
            $table->foreignId('rep_visit_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('rep_visit_id')->nullable()->after('cashier_shift_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropConstrainedForeignId('rep_visit_id'));
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('rep_visit_id');
            $t->dropColumn('source');
        });
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['latitude', 'longitude']));
        Schema::dropIfExists('rep_visits');
    }
};
