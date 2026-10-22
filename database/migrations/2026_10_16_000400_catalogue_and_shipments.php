<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Category → Brand → Product hierarchy, product origin / tax / discount / picture,
 * per-line tax on sales documents, and upcoming shipments (not yet in stock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
        // A brand name may repeat under different categories.
        Schema::table('brands', function (Blueprint $table) {
            $table->dropUnique('brands_name_unique');
            $table->unique(['category_id', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('made_in', 60)->nullable()->after('brand_id'); // country of origin
            $table->decimal('tax_rate', 5, 2)->default(0)->after('min_price'); // VAT / tax %
            $table->decimal('discount_percent', 5, 2)->default(0)->after('tax_rate'); // standard discount %
            $table->string('image_path')->nullable()->after('description');
        });

        foreach (['sales_order_items', 'quotation_items', 'invoice_items'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->decimal('tax_percent', 5, 2)->default(0)->after('discount_percent');
                $table->decimal('tax_amount', 14, 2)->default(0)->after('line_total');
            });
        }

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('reference', 100)->nullable(); // container / BL / supplier invoice
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('expected_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending, partial, received, cancelled
            $table->string('source_file')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('received_quantity', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->timestamps();
            $table->index('product_id');
        });

        Permissions::sync();
        Permissions::grantDefaults(['shipments.manage', 'shipments.view']);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        foreach (['sales_order_items', 'quotation_items', 'invoice_items'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn(['tax_percent', 'tax_amount']));
        }
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['made_in', 'tax_rate', 'discount_percent', 'image_path']));
        Schema::table('brands', function (Blueprint $table) {
            $table->dropUnique(['category_id', 'name']);
            $table->dropConstrainedForeignId('category_id');
            $table->unique('name');
        });
        DB::table('permissions')->whereIn('key', ['shipments.manage', 'shipments.view'])->delete();
    }
};
