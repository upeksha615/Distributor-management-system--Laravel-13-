<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An incoming payment slip (manual upload, email, WhatsApp or dealer portal) waiting to become a payment. */
class PaymentSubmission extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'matched' => 'Dealer matched',
        'info_requested' => 'Information requested',
        'converted' => 'Payment created',
        'rejected' => 'Rejected',
        'duplicate' => 'Duplicate',
    ];

    public const CHANNELS = ['manual' => 'Manual upload', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'portal' => 'Dealer portal'];

    protected $guarded = ['id'];

    protected $casts = [
        'claimed_amount' => 'decimal:2',
        'claimed_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['new', 'matched', 'info_requested'], true);
    }
}
