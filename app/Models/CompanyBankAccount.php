<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyBankAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_date' => 'date',
        'receives_dealer_payments' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function branch()
    {
        return $this->belongsTo(BankBranch::class, 'bank_branch_id');
    }

    /** "1234567890" → "••••••7890" unless the user may see full numbers. */
    public function maskedNumber(?User $user = null): string
    {
        $user ??= auth()->user();
        if ($user?->can('bank_accounts.view_numbers')) {
            return $this->account_number;
        }

        return str_repeat('•', max(0, strlen($this->account_number) - 4)).substr($this->account_number, -4);
    }

    /** Short label for dropdowns, e.g. "BOC Current ••••7890 (Main a/c)". */
    public function label(?User $user = null): string
    {
        return trim($this->bank->displayName().' '.ucfirst($this->account_type).' '.$this->maskedNumber($user).' ('.$this->account_name.')');
    }
}
