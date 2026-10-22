<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Minimum data to start using the system. Change the admin password after first login.
     */
    public function run(): void
    {
        \App\Support\Permissions::sync();

        User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Administrator',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        Warehouse::firstOrCreate(['name' => 'Main Store'], ['location' => 'Head office', 'is_active' => true]);
    }
}
