<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Role-based access control: roles, permissions and the role → permission matrix. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false); // seeded roles cannot be deleted
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('group', 50);
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('sales_rep')->change();
            // Dealer portal users are linked to their dealer account.
            $table->foreignId('customer_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Permissions::sync();

        foreach (Permissions::LEGACY as $old => $new) {
            DB::table('users')->where('role', $old)->update(['role' => $new]);
        }
    }

    public function down(): void
    {
        foreach (Permissions::LEGACY as $old => $new) {
            DB::table('users')->where('role', $new)->update(['role' => $old]);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('last_login_at');
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
