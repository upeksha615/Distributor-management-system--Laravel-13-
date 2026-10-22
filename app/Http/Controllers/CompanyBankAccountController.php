<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\CompanyBankAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The company's own bank accounts (where dealers pay in, and that suppliers are paid from). */
class CompanyBankAccountController extends Controller
{
    public function index()
    {
        return view('bank-accounts.index', ['accounts' => CompanyBankAccount::with(['bank', 'branch'])->orderByDesc('is_active')->orderBy('account_name')->get()]);
    }

    public function create()
    {
        return view('bank-accounts.form', ['account' => new CompanyBankAccount(['currency' => 'LKR', 'account_type' => 'current', 'is_active' => true, 'receives_dealer_payments' => true])] + $this->formData());
    }

    public function store(Request $request)
    {
        $account = CompanyBankAccount::create($this->validated($request));
        AuditLog::record('bank_account.create', $account, "Added company bank account {$account->label()}.");

        return redirect()->route('bank-accounts.index')->with('success', 'Bank account added.');
    }

    public function edit(CompanyBankAccount $bankAccount)
    {
        return view('bank-accounts.form', ['account' => $bankAccount] + $this->formData());
    }

    public function update(Request $request, CompanyBankAccount $bankAccount)
    {
        $bankAccount->fill($this->validated($request, $bankAccount));
        if ($bankAccount->isDirty()) {
            AuditLog::record('bank_account.update', $bankAccount, "Changed company bank account {$bankAccount->account_name}: ".implode(', ', array_keys($bankAccount->getDirty())));
        }
        $bankAccount->save();

        return redirect()->route('bank-accounts.index')->with('success', 'Bank account updated.');
    }

    private function formData(): array
    {
        return ['banks' => Bank::where('is_active', true)->orderBy('name')->get()];
    }

    private function validated(Request $request, ?CompanyBankAccount $account = null): array
    {
        return $request->validate([
            'bank_id' => ['required', 'exists:banks,id'],
            'bank_branch_id' => ['nullable', Rule::exists('bank_branches', 'id')->where('bank_id', $request->bank_id)],
            'account_name' => ['required', 'max:255'],
            'account_number' => ['required', 'max:40', 'regex:/^[0-9A-Za-z\- ]+$/', Rule::unique('company_bank_accounts')->where('bank_id', $request->bank_id)->ignore($account)],
            'account_type' => ['required', 'in:current,savings'],
            'currency' => ['required', 'size:3'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'max:255'],
        ]) + ['is_active' => $request->boolean('is_active'), 'receives_dealer_payments' => $request->boolean('receives_dealer_payments')];
    }
}
