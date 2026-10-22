<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankTest extends TestCase
{
    use RefreshDatabase;

    public function test_cbsl_licensed_banks_are_seeded(): void
    {
        $this->assertSame(24, Bank::where('type', 'commercial')->count());
        $this->assertSame(6, Bank::where('type', 'specialised')->count());
        $this->assertTrue(Bank::where('name', 'Bank of Ceylon')->where('short_name', 'BOC')->exists());
    }

    public function test_branches_can_be_added_and_imported_without_duplicates(): void
    {
        $admin = $this->user('accountant');
        $boc = Bank::where('name', 'Bank of Ceylon')->first();

        $this->actingAs($admin)->post("/banks/{$boc->id}/branches", ['name' => 'Kandy', 'code' => '004', 'city' => 'Kandy'])->assertRedirect();
        $this->actingAs($admin)->post("/banks/{$boc->id}/branches", ['name' => 'Kandy'])->assertSessionHasErrors('name');

        $csv = UploadedFile::fake()->createWithContent('branches.csv', "name,code,city\nKandy,004,Kandy City\nGalle,012,Galle\nMatara,,Matara\n");
        $this->actingAs($admin)->post("/banks/{$boc->id}/branches/import", ['file' => $csv])->assertSessionHas('success');

        $this->assertSame(3, $boc->branches()->count());
        $this->assertSame('Kandy City', $boc->branches()->where('name', 'Kandy')->value('city'));
        $this->actingAs($admin)->getJson("/banks/{$boc->id}/branch-lookup?q=gal")->assertJsonCount(1)->assertJsonPath('0.code', '012');
    }

    public function test_company_account_numbers_are_masked_for_roles_without_permission(): void
    {
        $bank = Bank::where('name', 'Sampath Bank PLC')->first();
        $accountant = $this->user('accountant');
        $this->actingAs($accountant)->post('/bank-accounts', [
            'bank_id' => $bank->id, 'account_name' => 'Main collection', 'account_number' => '1002003004',
            'account_type' => 'current', 'currency' => 'LKR', 'receives_dealer_payments' => 1, 'is_active' => 1,
        ])->assertRedirect('/bank-accounts');

        $account = CompanyBankAccount::first();
        $this->assertSame('1002003004', $account->maskedNumber($accountant));
        $this->assertSame('••••••3004', $account->maskedNumber($this->user('office_admin')));

        // Same bank + number twice is refused.
        $this->actingAs($accountant)->post('/bank-accounts', [
            'bank_id' => $bank->id, 'account_name' => 'Dup', 'account_number' => '1002003004', 'account_type' => 'current', 'currency' => 'LKR',
        ])->assertSessionHasErrors('account_number');

        $this->actingAs($this->user('office_admin'))->get('/bank-accounts')->assertForbidden();
    }

    public function test_settings_store_approval_limits_and_cheque_policy(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->put('/settings', [
            'company_name' => 'Test Distributors (Pvt) Ltd', 'currency' => 'Rs.', 'tax_rate' => 18,
            'cheque_recognition' => 'on_verification', 'payment_verify_limit' => 250000, 'credit_note_approval_limit' => 50000,
            'max_upload_mb' => 5, 'payment_segregation' => 1, 'credit_note_segregation' => 1, 'stock_low_threshold' => 10,
        ])->assertSessionHas('success');

        \Illuminate\Support\Facades\Cache::forget('settings');
        \App\Support\Settings::apply();
        $this->assertSame('on_verification', config('distributor.cheque_recognition'));
        $this->assertEquals(250000, config('distributor.payment_verify_limit'));

        $this->actingAs($admin)->put('/settings', ['company_name' => 'X', 'currency' => 'Rs.', 'tax_rate' => 0, 'cheque_recognition' => 'whenever',
            'payment_verify_limit' => 0, 'credit_note_approval_limit' => 0, 'max_upload_mb' => 5])->assertSessionHasErrors('cheque_recognition');
    }
}
