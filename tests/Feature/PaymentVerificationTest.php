<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Services\PaymentService;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The mandatory financial rules for dealer payments (spec section 22). */
class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $dealer;

    private CompanyBankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dealer = $this->dealer();
        $this->account = CompanyBankAccount::create(['bank_id' => Bank::where('short_name', 'BOC')->value('id'), 'account_name' => 'Main', 'account_number' => '7001234567']);
    }

    private function invoice(float $amount): Invoice
    {
        $product = $this->product(100, $amount);
        $this->actingAs($this->user('general_manager'));

        return app(SalesService::class)->createInvoice([
            'customer_id' => $this->dealer->id, 'warehouse_id' => $this->warehouse()->id, 'invoice_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $amount]],
        ]);
    }

    private function record(array $data, array $alloc = [], ?\App\Models\User $as = null): Payment
    {
        $this->actingAs($as ?? $this->user('office_admin'));

        return app(PaymentService::class)->record($this->dealer, $data + [
            'payment_date' => today()->toDateString(), 'method' => 'bank_transfer', 'company_bank_account_id' => $this->account->id,
        ], $alloc, empty($alloc));
    }

    public function test_pending_payment_does_not_reduce_balance_until_verified(): void
    {
        $inv = $this->invoice(10000);
        $payment = $this->record(['amount' => 10000, 'reference' => 'TRX-1']);

        $this->assertSame('pending_verification', $payment->status);
        $this->assertEquals(10000, $inv->fresh()->balance(), 'pending money must not settle the invoice');
        $this->assertEquals(10000, $this->dealer->fresh()->outstandingBalance());
        $this->assertEquals(10000, $this->dealer->fresh()->pendingPaymentsTotal());

        app(PaymentService::class)->verify($payment, $this->user('accountant'));
        $this->assertEquals(0, $inv->fresh()->balance());
        $this->assertSame('paid', $inv->fresh()->status);
        $this->assertEquals(0, $this->dealer->fresh()->outstandingBalance());
    }

    public function test_person_who_entered_cannot_verify_and_limits_apply(): void
    {
        $this->invoice(900000);
        $accountant = $this->user('accountant');
        $own = $this->record(['amount' => 1000, 'reference' => 'OWN-1'], [], $accountant);
        $this->expectValidation(fn () => app(PaymentService::class)->verify($own, $accountant), 'another authorised person');

        $big = $this->record(['amount' => 600000, 'reference' => 'BIG-1']); // default limit 500,000
        $this->expectValidation(fn () => app(PaymentService::class)->verify($big, $this->user('accountant')), 'above');
        app(PaymentService::class)->verify($big, $this->user('finance_manager'));
        $this->assertSame('verified', $big->fresh()->status);

        // Office admin has no verify permission at all.
        $this->actingAs($this->user('office_admin'))->post("/payments/{$own->id}/verify")->assertForbidden();
    }

    public function test_one_payment_across_invoices_partial_payments_and_advances(): void
    {
        $a = $this->invoice(3000);
        $b = $this->invoice(5000);
        $svc = app(PaymentService::class);

        $p1 = $this->record(['amount' => 6000, 'reference' => 'MULTI'], [$a->id => 3000, $b->id => 3000]);
        $svc->verify($p1, $this->user('accountant'));
        $this->assertSame('paid', $a->fresh()->status);
        $this->assertEquals(2000, $b->fresh()->balance());

        $p2 = $this->record(['amount' => 500, 'reference' => 'PART-2'], [$b->id => 500]);
        $svc->verify($p2, $this->user('accountant'));
        $this->assertEquals(1500, $b->fresh()->balance());
        $this->assertSame('partial', $b->fresh()->status);

        // Allocation larger than the payment is refused.
        $p3 = $this->record(['amount' => 1000, 'reference' => 'ADV-3'], [], null);
        $p3->allocations()->delete();
        $this->expectValidation(fn () => $svc->allocate($p3->fresh(), [$b->id => 1200]), 'more than the available');

        // Advance: verified without allocation, then allocated.
        $svc->verify($p3->fresh(), $this->user('accountant'));
        $this->assertEquals(1000, $this->dealer->fresh()->unallocatedCredit());
        $svc->allocate($p3->fresh(), [$b->id => 1000]);
        $this->assertEquals(500, $b->fresh()->balance());
        $this->assertEquals(500, $this->dealer->fresh()->outstandingBalance());
    }

    public function test_cheque_counts_only_when_cleared_and_return_reverses_it(): void
    {
        $inv = $this->invoice(20000);
        $svc = app(PaymentService::class);
        $cheque = $this->record(['amount' => 20000, 'method' => 'cheque', 'cheque_number' => '000123', 'bank_id' => Bank::first()->id,
            'cheque_date' => today()->toDateString(), 'company_bank_account_id' => null]);

        $svc->verify($cheque, $this->user('accountant'));
        $this->assertEquals(20000, $inv->fresh()->balance(), 'verified but uncleared cheque must not settle');

        $acc = $this->user('accountant');
        $svc->depositCheque($cheque->fresh(), $acc, today()->toDateString(), 'BOC', $this->account->id);
        $this->assertEquals(20000, $inv->fresh()->balance(), 'deposited is not cleared');

        $svc->clearCheque($cheque->fresh(), $acc, today()->toDateString());
        $this->assertEquals(0, $inv->fresh()->balance());

        $svc->returnCheque($cheque->fresh(), today()->toDateString(), 'Insufficient funds', 350, $acc);
        $c = $cheque->fresh();
        $this->assertSame('reversed', $c->status);
        $this->assertSame('returned', $c->cheque_status);
        $this->assertEquals(350, $c->bank_charges);
        $this->assertEquals(20000, $inv->fresh()->balance(), 'returned cheque restores the outstanding balance');
        $this->assertSame(['received', 'deposited', 'cleared', 'returned'], $c->history()->reorder('id')->where('field', 'cheque_status')->pluck('to')->all());
    }

    public function test_duplicates_are_flagged_and_must_be_reviewed(): void
    {
        $this->invoice(50000);
        $svc = app(PaymentService::class);
        $first = $this->record(['amount' => 5000, 'reference' => 'HNB-998877']);
        $second = $this->record(['amount' => 5000, 'reference' => 'HNB-998877']);

        $this->assertNull($first->fresh()->duplicate_check);
        $this->assertSame('flagged', $second->fresh()->duplicate_check);
        $this->expectValidation(fn () => $svc->verify($second->fresh(), $this->user('accountant')), 'duplicate');

        $svc->reject($second->fresh(), $this->user('office_admin'), 'Same slip sent twice', true);
        $this->assertSame('rejected', $second->fresh()->status);
        $this->assertSame(0, $second->fresh()->allocations()->count());

        // Same bank + cheque number across dealers is also caught.
        $bank = Bank::first();
        $this->record(['amount' => 100, 'method' => 'cheque', 'cheque_number' => '555', 'bank_id' => $bank->id, 'cheque_date' => today()->toDateString()]);
        $dupCheque = $this->record(['amount' => 200, 'method' => 'cheque', 'cheque_number' => '555', 'bank_id' => $bank->id, 'cheque_date' => today()->toDateString()]);
        $this->assertSame('flagged', $dupCheque->fresh()->duplicate_check);
    }

    public function test_verified_payment_is_reversed_not_deleted_and_cannot_be_verified_twice(): void
    {
        $inv = $this->invoice(4000);
        $svc = app(PaymentService::class);
        $p = $this->record(['amount' => 4000, 'reference' => 'REV-1']);
        $svc->verify($p, $this->user('accountant'));
        $this->expectValidation(fn () => $svc->verify($p->fresh(), $this->user('accountant')), 'not possible');

        $this->expectValidation(fn () => $svc->reverse($p->fresh(), $this->user('accountant'), 'x'), null, 403);
        $svc->reverse($p->fresh(), $this->user('finance_manager'), 'Bank recalled the transfer');
        $this->assertSame('reversed', $p->fresh()->status);
        $this->assertEquals(4000, $inv->fresh()->balance());
        $this->assertDatabaseHas('payments', ['id' => $p->id]);
        $this->assertTrue(\App\Models\AuditLog::where('action', 'payment.reversed')->exists());
    }

    public function test_receipt_only_after_verification(): void
    {
        $this->invoice(1000);
        $p = $this->record(['amount' => 1000, 'reference' => 'RCPT-T']);
        $gm = $this->user('general_manager');
        $this->actingAs($gm)->get("/payments/{$p->id}/pdf")->assertForbidden();
        app(PaymentService::class)->verify($p, $this->user('accountant'));
        $this->actingAs($gm)->get("/payments/{$p->id}/pdf")->assertOk();
    }

    public function test_slip_inbox_upload_duplicate_warning_and_conversion(): void
    {
        Storage::fake('local');
        $this->invoice(7500);
        $office = $this->user('office_admin');
        $file = UploadedFile::fake()->create('slip.jpg', 80, 'image/jpeg');

        $this->actingAs($office)->post('/payment-inbox', [
            'channel' => 'whatsapp', 'sender' => '+94771234567', 'customer_id' => $this->dealer->id, 'claimed_amount' => 7500,
            'claimed_date' => today()->toDateString(), 'claimed_method' => 'bank_transfer', 'claimed_reference' => 'SAMP-4455',
            'company_bank_account_id' => $this->account->id, 'files' => [$file],
        ])->assertRedirect();

        $s = PaymentSubmission::first();
        $this->assertSame('matched', $s->status);
        $doc = $s->attachments()->first();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringNotContainsString('public', $doc->path);

        $this->actingAs($office)->post("/payment-inbox/{$s->id}/convert", [
            'customer_id' => $this->dealer->id, 'amount' => 7500, 'payment_date' => today()->toDateString(), 'method' => 'bank_transfer',
            'reference' => 'SAMP-4455', 'company_bank_account_id' => $this->account->id, 'allocation_mode' => 'auto',
        ])->assertRedirect();

        $payment = Payment::first();
        $this->assertSame('pending_verification', $payment->status);
        $this->assertSame('whatsapp', $payment->channel);
        $this->assertSame(1, $payment->attachments()->count());
        $this->assertSame('converted', $s->fresh()->status);

        // A second slip with the same reference raises a warning.
        $this->actingAs($office)->post('/payment-inbox', ['channel' => 'email', 'claimed_reference' => 'SAMP-4455', 'files' => [UploadedFile::fake()->create('again.pdf', 20, 'application/pdf')]])
            ->assertSessionHas('error');

        // Attachments are served only to authorised users.
        $this->actingAs($office)->get("/attachments/{$doc->id}")->assertOk();
        $this->actingAs($this->user('inventory_manager'))->get("/attachments/{$doc->id}")->assertForbidden();
        $this->actingAs($this->user('sales_rep'))->get("/attachments/{$doc->id}")->assertForbidden(); // not their dealer
    }

    public function test_uploads_reject_unsafe_file_types(): void
    {
        $this->actingAs($this->user('office_admin'))->post('/payment-inbox', [
            'channel' => 'manual', 'files' => [UploadedFile::fake()->create('evil.php', 5, 'application/x-php')],
        ])->assertSessionHasErrors('files.0');
    }

    private function expectValidation(callable $fn, ?string $contains, int $httpStatus = 0): void
    {
        try {
            $fn();
            $this->fail('Expected the action to be refused.');
        } catch (ValidationException $e) {
            if ($contains) {
                $this->assertStringContainsStringIgnoringCase($contains, collect($e->errors())->flatten()->join(' '));
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame($httpStatus ?: $e->getStatusCode(), $e->getStatusCode());
        }
    }
}
