<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank & branch master and the company's own bank accounts.
 * Banks are seeded from the Central Bank of Sri Lanka list of licensed banks (as at 31 May 2026,
 * https://www.cbsl.gov.lk/en/node/2940). Codes and branches are maintained by administrators.
 */
return new class extends Migration
{
    private const COMMERCIAL = [
        'Amana Bank PLC', 'Bank of Ceylon', 'Bank of China Limited', 'Cargills Bank PLC', 'Citibank, N.A.',
        'Commercial Bank of Ceylon PLC', 'Deutsche Bank AG, Colombo Branch', 'DFCC Bank PLC', 'Habib Bank Ltd',
        'Hatton National Bank PLC', 'Indian Bank', 'Indian Overseas Bank', 'MCB Bank Ltd', 'National Development Bank PLC',
        'Nations Trust Bank PLC', 'Pan Asia Banking Corporation PLC', "People's Bank", 'Public Bank Berhad', 'Sampath Bank PLC',
        'Seylan Bank PLC', 'Standard Chartered Bank', 'State Bank of India', 'The Hongkong & Shanghai Banking Corporation Ltd (HSBC)',
        'Union Bank of Colombo PLC',
    ];

    private const SPECIALISED = [
        'Housing Development Finance Corporation Bank of Sri Lanka (HDFC)', 'National Savings Bank', 'Pradeshiya Sanwardhana Bank',
        'Sanasa Development Bank PLC', 'Sri Lanka Savings Bank Ltd', 'State Mortgage & Investment Bank',
    ];

    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->nullable()->unique(); // e.g. CEFTS / SLIPS bank code, entered by admin
            $table->string('name')->unique();
            $table->string('short_name', 30)->nullable();
            $table->string('type', 20)->default('commercial'); // commercial, specialised, other
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bank_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10)->nullable();
            $table->string('name');
            $table->string('city', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['bank_id', 'name']);
            $table->index(['bank_id', 'code']);
        });

        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_name');
            $table->string('account_number', 40);
            $table->string('account_type', 20)->default('current'); // current, savings
            $table->string('currency', 3)->default('LKR');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_date')->nullable();
            $table->boolean('receives_dealer_payments')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['bank_id', 'account_number']);
        });

        $now = now();
        foreach ([...array_map(fn ($n) => [$n, 'commercial'], self::COMMERCIAL), ...array_map(fn ($n) => [$n, 'specialised'], self::SPECIALISED)] as [$name, $type]) {
            DB::table('banks')->insertOrIgnore(['name' => $name, 'type' => $type, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $short = ['Bank of Ceylon' => 'BOC', "People's Bank" => "People's", 'Commercial Bank of Ceylon PLC' => 'ComBank', 'Hatton National Bank PLC' => 'HNB',
            'Sampath Bank PLC' => 'Sampath', 'Seylan Bank PLC' => 'Seylan', 'National Development Bank PLC' => 'NDB', 'DFCC Bank PLC' => 'DFCC',
            'Nations Trust Bank PLC' => 'NTB', 'Pan Asia Banking Corporation PLC' => 'Pan Asia', 'Union Bank of Colombo PLC' => 'Union Bank',
            'National Savings Bank' => 'NSB', 'The Hongkong & Shanghai Banking Corporation Ltd (HSBC)' => 'HSBC', 'Standard Chartered Bank' => 'SCB',
            'Cargills Bank PLC' => 'Cargills', 'Amana Bank PLC' => 'Amana'];
        foreach ($short as $name => $s) {
            DB::table('banks')->where('name', $name)->update(['short_name' => $s]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
        Schema::dropIfExists('bank_branches');
        Schema::dropIfExists('banks');
    }
};
