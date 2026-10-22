<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A dealer payment. Money only counts against invoices once it is "settled":
 * verified by an accountant and, for cheques under the default policy, cleared by the bank.
 */
class Payment extends Model
{
    public const METHODS = [
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'bank_transfer' => 'Bank transfer (online / mobile)',
        'bank_deposit' => 'Cash deposit at bank',
        'card' => 'Card',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'pending_verification' => 'Pending verification',
        'under_review' => 'Under review',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'reversed' => 'Reversed',
    ];

    /** Statuses still waiting for a decision. */
    public const OPEN_STATUSES = ['draft', 'submitted', 'pending_verification', 'under_review'];

    public const CHEQUE_STATUSES = [
        'received' => 'Received (in hand)',
        'deposited' => 'Deposited – awaiting clearance',
        'cleared' => 'Cleared',
        'returned' => 'Returned / bounced',
        'cancelled' => 'Cancelled',
    ];

    public const CHANNELS = ['office' => 'Office', 'rep' => 'Sales rep', 'inbox' => 'Slip inbox', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'portal' => 'Dealer portal'];

    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'cheque_date' => 'date',
        'returned_date' => 'date',
        'deposited_date' => 'date',
        'cleared_date' => 'date',
        'verified_at' => 'datetime',
        'reversed_at' => 'datetime',
        'amount' => 'decimal:2',
        'bank_charges' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** The user who entered the payment. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reverser()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function repVisit()
    {
        return $this->belongsTo(RepVisit::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function branch()
    {
        return $this->belongsTo(BankBranch::class, 'bank_branch_id');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function attachments()
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }

    public function history()
    {
        return $this->hasMany(PaymentStatusHistory::class)->latest('id');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(Payment::class, 'duplicate_of_id');
    }

    public function submission()
    {
        return $this->hasOne(PaymentSubmission::class);
    }

    /** Payments whose money counts against invoices and dealer balances. */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('payments.status', 'verified')->where(function ($q) {
            $q->where('payments.method', '!=', 'cheque');
            if (config('distributor.cheque_recognition') === 'on_verification') {
                $q->orWhereIn('payments.cheque_status', ['received', 'deposited', 'cleared']);
            } else {
                $q->orWhere('payments.cheque_status', 'cleared');
            }
        });
    }

    /** Payments waiting for verification (not yet counted). */
    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query->whereIn('payments.status', self::OPEN_STATUSES);
    }

    public function isSettled(): bool
    {
        if ($this->status !== 'verified') {
            return false;
        }
        if ($this->method !== 'cheque') {
            return true;
        }

        return config('distributor.cheque_recognition') === 'on_verification'
            ? in_array($this->cheque_status, ['received', 'deposited', 'cleared'], true)
            : $this->cheque_status === 'cleared';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst(str_replace('_', ' ', $this->method));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Badge text including the derived allocation state for verified payments. */
    public function displayStatus(): string
    {
        if ($this->status === 'verified' && $this->method === 'cheque' && ! $this->isSettled()) {
            return 'verified – cheque not cleared';
        }
        if ($this->status !== 'verified') {
            return strtolower($this->statusLabel());
        }
        $left = $this->unallocatedAmount();

        return match (true) {
            $left <= 0 => 'fully allocated',
            $left < (float) $this->amount => 'partially allocated',
            default => 'verified – unallocated',
        };
    }

    /** Part of the payment not applied to invoices. */
    public function unallocatedAmount(): float
    {
        return round((float) $this->amount - (float) $this->allocations()->sum('amount'), 2);
    }

    /** Cheque register status (in hand / deposited / cleared / returned). */
    public function chequeStatus(): ?string
    {
        if ($this->method !== 'cheque') {
            return null;
        }

        return match ($this->cheque_status) {
            'received', null => 'in hand',
            default => $this->cheque_status,
        };
    }
}
