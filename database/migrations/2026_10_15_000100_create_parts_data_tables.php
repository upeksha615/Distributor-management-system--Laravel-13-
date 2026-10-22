<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spare-parts specific product data, price levels, settings and audit log. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('part_number', 100)->nullable()->after('sku')->index(); // OEM / manufacturer number
            $table->string('barcode', 100)->nullable()->after('part_number')->index();
            $table->decimal('wholesale_price', 14, 2)->default(0)->after('selling_price');
            $table->decimal('min_price', 14, 2)->default(0)->after('wholesale_price'); // lowest price a cashier may sell at
            $table->string('location', 50)->nullable()->after('reorder_level'); // rack / bin
        });

        // Cross references: alternate OEM numbers and other brands' equivalent numbers.
        Schema::create('product_part_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('number', 100)->index();
            $table->string('type', 20)->default('alternate'); // oem, alternate, supplier
            $table->string('brand', 100)->nullable();
            $table->timestamps();
        });

        // Which vehicles a part fits.
        Schema::create('product_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('make', 50)->index();
            $table->string('model', 100)->nullable()->index();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('engine', 50)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('price_level', 20)->default('wholesale')->after('payment_terms_days'); // retail, wholesale
            $table->boolean('is_walk_in')->default(false)->after('on_hold');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->default(30)->after('country');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50)->index();
            $table->nullableMorphs('subject');
            $table->text('description');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('payment_terms_days'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['price_level', 'is_walk_in']));
        Schema::dropIfExists('product_fitments');
        Schema::dropIfExists('product_part_numbers');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['part_number', 'barcode', 'wholesale_price', 'min_price', 'location']));
    }
};
