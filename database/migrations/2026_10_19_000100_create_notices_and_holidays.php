<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Short notices shown to every user on the dashboard.
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->string('level', 20)->default('info'); // info, important
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });

        // Public / bank / mercantile holidays shown in the dashboard calendar.
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name', 150);
            $table->boolean('is_bank')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_mercantile')->default(false);
            $table->timestamps();
            $table->unique(['date', 'name']);
        });

        // 2026 list from the Central Bank of Sri Lanka (cbsl.gov.lk, "Bank Holidays for 2026"). B = bank, P = public, M = mercantile.
        $list = [
            ['2026-01-03', 'Duruthu Full Moon Poya Day', 'BP'],
            ['2026-01-15', 'Tamil Thai Pongal Day', 'BPM'],
            ['2026-02-01', 'Navam Full Moon Poya Day', 'BP'],
            ['2026-02-04', 'Independence Day', 'BPM'],
            ['2026-02-15', 'Mahasivarathri Day', 'BP'],
            ['2026-03-02', 'Medin Full Moon Poya Day', 'BP'],
            ['2026-03-21', 'Id-Ul-Fitre (Ramazan Festival Day)', 'BP'],
            ['2026-04-01', 'Bak Full Moon Poya Day', 'BP'],
            ['2026-04-03', 'Good Friday', 'BP'],
            ['2026-04-13', 'Day prior to Sinhala & Tamil New Year Day', 'BPM'],
            ['2026-04-14', 'Sinhala & Tamil New Year Day', 'BPM'],
            ['2026-05-01', 'Vesak Full Moon Poya Day', 'BP'],
            ['2026-05-01', "May Day (International Workers' Day)", 'BPM'],
            ['2026-05-02', 'Day following Vesak Full Moon Poya Day', 'BPM'],
            ['2026-05-28', 'Id-Ul-Allah (Hadji Festival Day)', 'BP'],
            ['2026-05-30', 'Adhi Poson Full Moon Poya Day', 'BP'],
            ['2026-06-29', 'Poson Full Moon Poya Day', 'BP'],
            ['2026-07-29', 'Esala Full Moon Poya Day', 'BP'],
            ['2026-08-26', "Milad-Un-Nabi (Holy Prophet's Birthday)", 'BPM'],
            ['2026-08-27', 'Nikini Full Moon Poya Day', 'BP'],
            ['2026-09-26', 'Binara Full Moon Poya Day', 'BP'],
            ['2026-10-25', 'Vap Full Moon Poya Day', 'BP'],
            ['2026-11-08', 'Deepawali Festival Day', 'BP'],
            ['2026-11-24', 'Ill Full Moon Poya Day', 'BP'],
            ['2026-12-23', 'Unduwap Full Moon Poya Day', 'BP'],
            ['2026-12-25', 'Christmas Day', 'BPM'],
        ];
        $now = now();
        DB::table('holidays')->insert(array_map(fn ($h) => [
            'date' => $h[0], 'name' => $h[1],
            'is_bank' => str_contains($h[2], 'B'), 'is_public' => str_contains($h[2], 'P'), 'is_mercantile' => str_contains($h[2], 'M'),
            'created_at' => $now, 'updated_at' => $now,
        ], $list));

        Permissions::sync();
        Permissions::grantDefaults(['notices.manage']);
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('notices');
    }
};
