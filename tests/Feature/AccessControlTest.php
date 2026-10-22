<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/invoices')->assertRedirect('/login');
    }

    public function test_login_works_and_inactive_users_are_refused(): void
    {
        $user = $this->user('accountant');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->post('/logout');

        $inactive = $this->user('accountant', ['is_active' => false]);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_role_permission_matrix_is_enforced_on_routes(): void
    {
        $cases = [
            // role => [allowed, forbidden]
            'office_admin' => [['/payments', '/payments/create', '/customers'], ['/supplier-bills', '/users', '/stock/adjust', '/reports/profit-loss']],
            'accountant' => [['/payments', '/cheques', '/supplier-bills', '/reports/profit-loss'], ['/users', '/roles', '/purchase-orders', '/stock/adjust']],
            'inventory_manager' => [['/products/create', '/stock/adjust', '/goods-receipts', '/reports/stock-valuation'], ['/payments', '/invoices', '/supplier-bills', '/users']],
            'purchasing_officer' => [['/suppliers', '/purchase-orders'], ['/invoices', '/payments', '/stock/adjust']],
            'auditor' => [['/invoices', '/payments', '/audit-log', '/reports/profit-loss'], ['/invoices/create', '/payments/create', '/products/create', '/users']],
            'sales_rep' => [['/rep', '/quotations', '/sales-orders'], ['/supplier-bills', '/reports/profit-loss', '/users', '/cheques/deposit']],
        ];

        foreach ($cases as $role => [$allowed, $forbidden]) {
            $user = $this->user($role);
            foreach ($allowed as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
            foreach ($forbidden as $url) {
                $status = $this->actingAs($user)->get($url)->status();
                $this->assertContains($status, [403, 405], "$role should not reach $url (got $status)");
            }
        }
    }

    public function test_sales_rep_only_sees_assigned_dealers(): void
    {
        $rep = $this->user('sales_rep');
        $mine = $this->dealer(['sales_rep_id' => $rep->id, 'name' => 'My Dealer']);
        $other = $this->dealer(['name' => 'Someone Elses Dealer']);

        $this->actingAs($rep)->get('/customers')->assertSee('My Dealer')->assertDontSee('Someone Elses Dealer');
        $this->actingAs($rep)->get("/customers/{$other->id}")->assertForbidden();
        $this->actingAs($rep)->get("/customers/{$mine->id}/statement")->assertOk();

        $invoice = Invoice::create([
            'number' => 'INV-TEST-1', 'customer_id' => $other->id, 'warehouse_id' => $this->warehouse()->id,
            'invoice_date' => today(), 'due_date' => today(), 'status' => 'unpaid', 'subtotal' => 100, 'total' => 100,
        ]);
        $this->actingAs($rep)->get("/invoices/{$invoice->id}")->assertForbidden();
        $this->actingAs($rep)->get("/invoices/{$invoice->id}/pdf")->assertForbidden();
        $this->actingAs($rep)->get('/invoices')->assertDontSee('INV-TEST-1');

        $manager = $this->user('general_manager');
        $this->actingAs($manager)->get("/invoices/{$invoice->id}")->assertOk();
    }

    public function test_dealer_portal_users_cannot_enter_the_back_office(): void
    {
        $dealerUser = $this->user('dealer', ['customer_id' => $this->dealer()->id]);
        $response = $this->actingAs($dealerUser)->get('/invoices');
        $this->assertTrue($response->isRedirect() || $response->status() === 403);
    }

    public function test_export_needs_permission(): void
    {
        $this->actingAs($this->user('sales_rep'))->get('/sales-orders?export=xlsx')->assertForbidden();
        $this->actingAs($this->user('general_manager'))->get('/sales-orders?export=csv')->assertOk()->assertDownload();
    }

    public function test_role_changes_are_audited_and_super_admin_is_protected(): void
    {
        $admin = $this->user('super_admin');
        $role = Role::where('slug', 'office_admin')->first();

        $this->actingAs($admin)->put("/roles/{$role->id}", ['name' => 'Office Admin', 'permissions' => ['payments.view', 'payments.record', 'payments.verify']])
            ->assertRedirect('/roles');
        $this->assertTrue($this->user('office_admin')->can('payments.verify'));
        $this->assertTrue(AuditLog::where('action', 'role.permissions')->where('description', 'like', '%granted payments.verify%')->exists());

        $superRole = Role::where('slug', 'super_admin')->first();
        $this->actingAs($admin)->put("/roles/{$superRole->id}", ['name' => 'x', 'permissions' => []])->assertForbidden();

        // A General Manager given users.manage still cannot create a Super Admin.
        $gmRole = Role::where('slug', 'general_manager')->first();
        $gmRole->permissions()->attach(\App\Models\Permission::where('key', 'users.manage')->value('id'));
        $this->actingAs($this->user('general_manager'))->post('/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@test.local', 'role' => 'super_admin', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden();
    }
}
