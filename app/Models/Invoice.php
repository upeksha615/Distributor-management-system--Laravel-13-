<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public const STATUSES = ['unpaid', 'partial', 'paid', 'cancelled'];

    protected $guarded = ['id'];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'credited_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    /** Invoices that still have money owing. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid_amount - (float) $this->credited_amount, 2);
    }

    public function daysOverdue(): int
    {
        return $this->due_date->isPast() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /** Amount claimed against this invoice by payments that are not yet verified / cleared (not counted). */
    public function pendingAmount(): float
    {
        return round((float) $this->allocations()->whereHas('payment', fn ($q) => $q->where(fn ($w) => $w
            ->whereIn('status', Payment::OPEN_STATUSES)
            ->orWhere(fn ($v) => $v->where('status', 'verified')->where('method', 'cheque')->whereNotIn('cheque_status', ['cleared', 'returned', 'cancelled'])))
        )->sum('amount'), 2);
    }

    /** Recalculate paid amount from settled allocations only (verified, and cleared for cheques) and set the status. */
    public function refreshPaymentStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $this->paid_amount = $this->allocations()
            ->whereHas('payment', fn ($q) => $q->settled())
            ->sum('amount');

        $balance = $this->balance();
        $this->status = match (true) {
            $balance <= 0 => 'paid',
            (float) $this->paid_amount + (float) $this->credited_amount > 0 => 'partial',
            default => 'unpaid',
        };
        $this->save();
    }
}
