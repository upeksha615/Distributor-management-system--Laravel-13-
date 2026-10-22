<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_super_admin_asking_for_the_password_privately(): void
    {
        $this->artisan('user:create-admin')
            ->expectsQuestion('Full name', 'Kamal Perera')
            ->expectsQuestion('Login email', 'kamal@company.lk')
            ->expectsQuestion('Password (min. 8 characters, not shown)', 'Str0ng-Pass')
            ->expectsQuestion('Repeat the password', 'Str0ng-Pass')
            ->assertSuccessful();

        $user = User::where('email', 'kamal@company.lk')->first();
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue(Hash::check('Str0ng-Pass', $user->password));
        $this->post('/login', ['email' => 'kamal@company.lk', 'password' => 'Str0ng-Pass'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_rejects_mismatched_or_weak_passwords(): void
    {
        $this->artisan('user:create-admin')
            ->expectsQuestion('Full name', 'A')->expectsQuestion('Login email', 'a@company.lk')
            ->expectsQuestion('Password (min. 8 characters, not shown)', 'Str0ng-Pass')->expectsQuestion('Repeat the password', 'different')
            ->assertFailed();
        $this->artisan('user:create-admin', ['--name' => 'A', '--email' => 'a@company.lk', '--password' => 'short'])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'a@company.lk']);
    }

    public function test_offers_to_disable_the_public_default_admin(): void
    {
        $this->seed();
        $this->assertTrue(User::where('email', 'admin@example.com')->value('is_active'));

        $this->artisan('user:create-admin', ['--name' => 'Owner', '--email' => 'owner@company.lk', '--password' => 'Str0ng-Pass'])
            ->expectsConfirmation('The default account admin@example.com (password "password") is still active. Disable it now?', 'yes')
            ->assertSuccessful();

        $this->assertFalse(User::where('email', 'admin@example.com')->value('is_active'));
        $this->assertSame('super_admin', User::where('email', 'owner@company.lk')->value('role'));
    }
}
