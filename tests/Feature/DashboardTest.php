<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Notice;
use App\Services\PaymentService;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_no_company_figures_or_dealer_names(): void
    {
        $manager = $this->user('general_manager');
        $dealer = $this->dealer(['name' => 'Secret Dealer Motors']);
        $this->actingAs($manager);
        app(SalesService::class)->createInvoice([
            'customer_id' => $dealer->id, 'warehouse_id' => $this->warehouse()->id, 'invoice_date' => today()->subDays(60)->toDateString(),
            'items' => [['product_id' => $this->product(37, 123456)->id, 'quantity' => 1, 'unit_price' => 123456]],
        ]);

        $page = $this->get('/')->assertOk();
        foreach (['123,456', 'Secret Dealer Motors', 'Rs.', 'INV-'] as $hidden) {
            $page->assertDontSee($hidden);
        }
        $page->assertSee('Quick actions')->assertSee('My tasks')->assertSee('Notice board');
        $this->get('/?show_values=1')->assertDontSee('123,456');
    }

    public function test_tasks_are_counts_limited_to_the_users_permissions(): void
    {
        $dealer = $this->dealer();
        $this->actingAs($this->user('office_admin'));
        app(PaymentService::class)->record($dealer, ['amount' => 5000, 'method' => 'cash', 'payment_date' => today()->toDateString()], [], false);

        $this->actingAs($this->user('accountant'))->get('/')->assertSee('Payments to verify')->assertDontSee('5,000');
        // The office admin cannot verify, so that task is not offered.
        $this->actingAs($this->user('office_admin'))->get('/')->assertDontSee('Payments to verify');
    }

    public function test_notices_show_only_inside_their_dates_and_only_admins_manage_them(): void
    {
        Notice::create(['title' => 'Office closed on Poya', 'level' => 'important', 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay()]);
        Notice::create(['title' => 'Old notice', 'level' => 'info', 'starts_on' => today()->subDays(10), 'ends_on' => today()->subDays(2)]);
        Notice::create(['title' => 'Future notice', 'level' => 'info', 'starts_on' => today()->addDays(3)]);

        $this->actingAs($this->user('accountant'))->get('/')->assertSee('Office closed on Poya')->assertDontSee('Old notice')->assertDontSee('Future notice');

        $this->actingAs($this->user('accountant'))->post('/notices', ['title' => 'x', 'level' => 'info', 'starts_on' => today()->toDateString()])->assertForbidden();
        $this->actingAs($this->user('office_admin'))->post('/notices', ['title' => 'Price list update', 'level' => 'info', 'starts_on' => today()->toDateString()])
            ->assertRedirect('/notices');
        $this->actingAs($this->user('accountant'))->get('/')->assertSee('Price list update');
    }

    public function test_holidays_from_the_central_bank_list_and_calendar(): void
    {
        $this->assertSame(26, Holiday::whereYear('date', 2026)->count());
        $this->assertTrue(Holiday::whereDate('date', '2026-04-14')->first()->is_mercantile);
        $this->assertFalse(Holiday::whereDate('date', '2026-10-25')->first()->is_mercantile);

        $this->travelTo('2026-10-19 09:00:00');
        $this->actingAs($this->user('accountant'))->get('/')->assertSee('October 2026')->assertSee('Vap Full Moon Poya Day')->assertSee('in 6 days');
        $this->travelBack();

        $admin = $this->user('office_admin');
        $this->actingAs($admin)->post('/holidays', ['date' => '2027-01-14', 'name' => 'Tamil Thai Pongal Day', 'is_bank' => 1, 'is_public' => 1, 'is_mercantile' => 1])
            ->assertSessionHas('success');
        $this->actingAs($admin)->post('/holidays', ['date' => '2027-01-14', 'name' => 'Tamil Thai Pongal Day'])->assertSessionHasErrors('name');
    }

    public function test_system_status_only_for_administrators(): void
    {
        $this->actingAs($this->user('super_admin'))->get('/')->assertSee('System status');
        $this->actingAs($this->user('general_manager'))->get('/')->assertDontSee('System status');
    }
}
