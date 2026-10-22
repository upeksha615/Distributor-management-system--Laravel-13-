<?php

namespace Tests\Feature;

use App\Mail\DailySummaryMail;
use App\Mail\InvoiceMail;
use App\Mail\OverdueReminderMail;
use App\Mail\PasswordResetMail;
use App\Mail\PaymentNoticeMail;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RepVisit;
use App\Models\TaskRun;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailBackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // A delivering mailer by default (Mail::fake() intercepts the actual sending).
        config(['mail.default' => 'smtp']);
    }

    private function invoiceFor($dealer, float $price = 1000, ?string $date = null): Invoice
    {
        $this->actingAs($this->user('general_manager'));

        return app(SalesService::class)->createInvoice([
            'customer_id' => $dealer->id, 'warehouse_id' => $this->warehouse()->id, 'invoice_date' => $date ?? today()->toDateString(),
            'items' => [['product_id' => $this->product(10, $price)->id, 'quantity' => 1, 'unit_price' => $price]],
        ]);
    }

    // ---------- Password reset ----------

    public function test_forgot_password_emails_a_working_one_time_link(): void
    {
        Mail::fake();
        $user = $this->user('accountant', ['email' => 'acc@test.local']);

        $this->get('/login')->assertSee('Forgot your password?');
        $this->post('/forgot-password', ['email' => 'acc@test.local'])->assertSessionHas('status');

        $url = null;
        Mail::assertSent(PasswordResetMail::class, function ($mail) use (&$url, $user) {
            $url = $mail->url;

            return $mail->hasTo($user->email);
        });
        $this->assertSame('sent', EmailLog::where('type', 'password_reset')->value('status'));

        // Open the link and choose a new password.
        $path = parse_url($url, PHP_URL_PATH);
        $token = basename($path);
        $this->get($path.'?email='.urlencode($user->email))->assertOk()->assertSee('Choose a new password', false);

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'NewPass#2026', 'password_confirmation' => 'NewPass#2026'])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPass#2026', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset', 'user_id' => $user->id]);

        // The same link cannot be used twice.
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Another#2026', 'password_confirmation' => 'Another#2026'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Auth::validate(['email' => $user->email, 'password' => 'NewPass#2026']));
    }

    public function test_forgot_password_does_not_reveal_accounts_or_help_inactive_users(): void
    {
        Mail::fake();
        $this->user('accountant', ['email' => 'gone@test.local', 'is_active' => false]);

        $unknown = $this->post('/forgot-password', ['email' => 'nobody@test.local'])->assertSessionHas('status')->getSession()->get('status');
        $inactive = $this->post('/forgot-password', ['email' => 'gone@test.local'])->assertSessionHas('status')->getSession()->get('status');
        $this->assertSame($unknown, $inactive);
        Mail::assertNothingSent();
        $this->assertSame(0, EmailLog::count());
    }

    public function test_admin_can_send_a_reset_link_but_others_cannot(): void
    {
        Mail::fake();
        $target = $this->user('sales_rep');
        $this->actingAs($this->user('accountant'))->post("/users/{$target->id}/send-reset")->assertForbidden();
        $this->actingAs($this->user('super_admin'))->post("/users/{$target->id}/send-reset")->assertSessionHas('success');
        Mail::assertSent(PasswordResetMail::class, fn ($m) => $m->hasTo($target->email));
    }

    // ---------- Email: invoices and dealer notices ----------

    public function test_invoice_is_emailed_with_pdf_and_logged(): void
    {
        Mail::fake();
        $dealer = $this->dealer(['email' => 'dealer@test.local']);
        $invoice = $this->invoiceFor($dealer);

        $this->actingAs($this->user('office_admin'))->get("/invoices/{$invoice->id}")->assertSee('Email invoice');
        $this->actingAs($this->user('office_admin'))->post("/invoices/{$invoice->id}/email", ['to' => 'dealer@test.local', 'note' => 'Thanks'])
            ->assertSessionHas('success');

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('dealer@test.local') && $mail->invoice->is($invoice)
            && str_starts_with($mail->pdf, '%PDF') && $mail->attachments()[0]->as === 'Invoice-'.$invoice->number.'.pdf');
        $this->assertDatabaseHas('email_logs', ['type' => 'invoice', 'to' => 'dealer@test.local', 'status' => 'sent', 'related_id' => $invoice->id]);

        // Reps do not have the permission; cancelled invoices cannot be sent.
        $this->actingAs($this->user('sales_rep'))->post("/invoices/{$invoice->id}/email", ['to' => 'x@test.local'])->assertForbidden();
        $invoice->update(['status' => 'cancelled']);
        $this->actingAs($this->user('office_admin'))->post("/invoices/{$invoice->id}/email", ['to' => 'x@test.local'])->assertSessionHas('error');
        Mail::assertSentCount(1);
    }

    public function test_log_mailer_is_reported_as_not_delivered_and_smtp_failure_does_not_break_the_action(): void
    {
        $dealer = $this->dealer(['email' => 'dealer@test.local']);
        $invoice = $this->invoiceFor($dealer);
        $office = $this->user('office_admin');

        config(['mail.default' => 'log']);
        $this->actingAs($office)->post("/invoices/{$invoice->id}/email", ['to' => 'dealer@test.local'])->assertSessionHas('warning');
        $this->assertSame('logged', EmailLog::latest('id')->value('status'));

        // Unreachable SMTP server: the email is marked failed, the user sees why.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $this->actingAs($office)->post("/invoices/{$invoice->id}/email", ['to' => 'dealer@test.local'])->assertSessionHas('error');
        $log = EmailLog::latest('id')->first();
        $this->assertSame('failed', $log->status);
        $this->assertNotEmpty($log->error);

        // A payment verification still succeeds when the dealer notice cannot be sent.
        $payment = app(PaymentService::class)->record($dealer, ['amount' => 500, 'method' => 'bank_transfer', 'payment_date' => today()->toDateString(), 'reference' => 'TT-1'], [], false);
        $this->actingAs($this->user('accountant'));
        app(PaymentService::class)->verify($payment, auth()->user());
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('failed', EmailLog::where('type', 'payment_verified')->value('status'));
    }

    public function test_dealer_is_notified_of_verified_rejected_and_returned_payments(): void
    {
        Mail::fake();
        $dealer = $this->dealer(['email' => 'dealer@test.local']);
        $this->invoiceFor($dealer, 5000);
        $svc = app(PaymentService::class);
        $this->actingAs($this->user('office_admin'));
        $transfer = $svc->record($dealer, ['amount' => 1000, 'method' => 'bank_transfer', 'payment_date' => today()->toDateString(), 'reference' => 'TT-9']);
        $wrong = $svc->record($dealer, ['amount' => 700, 'method' => 'bank_transfer', 'payment_date' => today()->toDateString(), 'reference' => 'TT-10'], [], false);
        $cheque = $svc->record($dealer, ['amount' => 2000, 'method' => 'cheque', 'payment_date' => today()->toDateString(), 'cheque_number' => '123456',
            'cheque_bank' => 'Test Bank', 'cheque_date' => today()->toDateString()], [], false);

        $acc = $this->user('accountant');
        $this->actingAs($acc);
        $svc->verify($transfer, $acc);
        $svc->reject($wrong, $acc, 'Not in our account');
        $svc->verify($cheque, $acc);
        $svc->returnCheque($cheque->fresh(), today()->toDateString(), 'Refer to drawer', 0, $acc);

        Mail::assertSent(PaymentNoticeMail::class, fn ($m) => $m->kind === 'verified' && $m->payment->is($transfer) && $m->hasTo('dealer@test.local'));
        Mail::assertSent(PaymentNoticeMail::class, fn ($m) => $m->kind === 'rejected' && $m->payment->is($wrong));
        Mail::assertSent(PaymentNoticeMail::class, fn ($m) => $m->kind === 'verified' && $m->payment->is($cheque) && str_contains($m->envelope()->subject, 'subject to clearance'));
        Mail::assertSent(PaymentNoticeMail::class, fn ($m) => $m->kind === 'returned' && $m->payment->is($cheque));
        $this->assertSame(4, EmailLog::count());
    }

    public function test_no_dealer_notice_when_switched_off_without_email_or_for_duplicates(): void
    {
        Mail::fake();
        $svc = app(PaymentService::class);
        $acc = $this->user('accountant');

        $noEmail = $this->dealer();
        $this->actingAs($this->user('office_admin'));
        $p1 = $svc->record($noEmail, ['amount' => 100, 'method' => 'cash', 'payment_date' => today()->toDateString()], [], false);
        $this->actingAs($acc);
        $svc->verify($p1, $acc);

        $dealer = $this->dealer(['email' => 'dealer@test.local']);
        $this->actingAs($this->user('office_admin'));
        $dup = $svc->record($dealer, ['amount' => 100, 'method' => 'cash', 'payment_date' => today()->toDateString()], [], false);
        $svc->reject($dup, auth()->user(), 'Entered twice', true);

        config(['distributor.notifications.dealer_payment_notices' => false]);
        $p2 = $svc->record($dealer, ['amount' => 150, 'method' => 'cash', 'payment_date' => today()->toDateString()], [], false);
        $this->actingAs($acc);
        $svc->verify($p2, $acc);

        Mail::assertNothingSent();
    }

    // ---------- Scheduled emails ----------

    public function test_daily_summary_goes_to_managers_with_the_permission(): void
    {
        Mail::fake();
        $gm = $this->user('general_manager');
        $rep = $this->user('sales_rep');
        $this->invoiceFor($this->dealer(), 1000, today()->subDays(60)->toDateString());

        $this->artisan('summary:daily')->assertSuccessful();

        Mail::assertSent(DailySummaryMail::class, fn ($m) => $m->hasTo($gm->email) && collect($m->summary['rows'])->firstWhere('label', 'Overdue dealer invoices')['count'] === 1);
        Mail::assertNotSent(DailySummaryMail::class, fn ($m) => $m->hasTo($rep->email));
        $this->assertSame('success', TaskRun::latestFor('summary:daily')->status);
    }

    public function test_overdue_reminders_only_when_enabled(): void
    {
        Mail::fake();
        $late = $this->dealer(['email' => 'late@test.local', 'payment_terms_days' => 30]);
        $this->invoiceFor($late, 1000, today()->subDays(45)->toDateString());
        $current = $this->dealer(['email' => 'ok@test.local']);
        $this->invoiceFor($current, 1000);

        $this->artisan('reminders:overdue')->assertSuccessful();
        Mail::assertNothingSent();

        config(['distributor.notifications.overdue_reminders' => true]);
        $this->artisan('reminders:overdue')->assertSuccessful();
        Mail::assertSent(OverdueReminderMail::class, fn ($m) => $m->hasTo('late@test.local') && $m->total == 1000);
        Mail::assertNotSent(OverdueReminderMail::class, fn ($m) => $m->hasTo('ok@test.local'));
    }

    public function test_stale_rep_visits_are_closed_but_todays_are_not(): void
    {
        $rep = $this->user('sales_rep');
        $dealer = $this->dealer();
        $old = RepVisit::create(['user_id' => $rep->id, 'customer_id' => $dealer->id, 'checked_in_at' => now()->subDay()->setTime(10, 0)]);
        $today = RepVisit::create(['user_id' => $rep->id, 'customer_id' => $dealer->id, 'checked_in_at' => now()->startOfDay()->addMinute()]);

        $this->artisan('visits:close-stale')->assertSuccessful();
        $this->assertNotNull($old->fresh()->checked_out_at);
        $this->assertStringContainsString('Auto-closed', $old->fresh()->notes);
        $this->assertNull($today->fresh()->checked_out_at);
    }

    public function test_tasks_are_scheduled_and_there_is_no_backup_feature(): void
    {
        $this->assertArrayNotHasKey('backup:run', \Illuminate\Support\Facades\Artisan::all());
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('system.backup'));
        $this->artisan('schedule:list')
            ->doesntExpectOutputToContain('backup:run')
            ->expectsOutputToContain('summary:daily')
            ->expectsOutputToContain('reminders:overdue')
            ->expectsOutputToContain('visits:close-stale')
            ->expectsOutputToContain('auth:clear-resets')
            ->assertSuccessful();
    }

    public function test_system_page_reports_scheduler_and_mail_state_and_sends_test_email(): void
    {
        Mail::fake();
        $admin = $this->user('super_admin');
        config(['mail.default' => 'log']);
        $this->actingAs($admin)->get('/system')->assertOk()->assertSee('The scheduler is not running')->assertSee('not being delivered', false);

        \Illuminate\Support\Facades\Cache::forever('scheduler:last_tick', now()->toIso8601String());
        config(['mail.default' => 'smtp']);
        $this->actingAs($admin)->get('/system')->assertSee('The scheduler is running');

        $this->actingAs($admin)->post('/system/test-email', ['to' => 'me@test.local'])->assertSessionHas('success');
        $this->actingAs($admin)->get('/system/emails')->assertOk()->assertSee('me@test.local');
    }
}
