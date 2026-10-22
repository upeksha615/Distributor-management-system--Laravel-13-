<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Reps see stock as a colour only; exact quantities need stock.view_qty. */
return new class extends Migration
{
    public function up(): void
    {
        Permissions::sync();
        Permissions::grantDefaults(['stock.view_qty']);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'stock.view_qty')->delete();
    }
};
