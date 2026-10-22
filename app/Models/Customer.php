<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
        'on_hold' => 'boolean',
        'is_walk_in' => 'boolean',
    ];

    public const PRICE_LEVELS = ['wholesale' => 'Wholesale / dealer', 'retail' => 'Retail'];

    public function salesRep()
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    /** Dealers the user may see: all with customers.view_all, otherwise only those assigned to them. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->canSeeAllDealers() ? $query : $query->where('sales_rep_id', $user->id);
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->canSeeAllDealers() || $this->sales_rep_id === $user->id;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('code', 'like', "%{$term}%")
            ->orWhere('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%"));
    }

    /** Net amount the customer owes: invoice balances less any unallocated payments. */
    public function outstandingBalance(): float
    {
        $invoiced = (float) $this->invoices()
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(total - paid_amount - credited_amount), 0) as balance')
            ->value('balance');

        return round($invoiced - $this->unallocatedCredit(), 2);
    }

    /** Payments submitted but not yet verified / cleared. Shown separately; never reduces the balance. */
    public function pendingPaymentsTotal(): float
    {
        return round((float) $this->payments()->where(fn ($q) => $q->whereIn('status', Payment::OPEN_STATUSES)
            ->orWhere(fn ($w) => $w->where('status', 'verified')->where('method', 'cheque')->whereNotIn('cheque_status', ['cleared', 'returned', 'cancelled'])
                ->when(config('distributor.cheque_recognition') === 'on_verification', fn ($x) => $x->whereRaw('1 = 0'))))
            ->sum('amount'), 2);
    }

    /** Verified (settled) money received but not yet allocated to an invoice (customer credit / advance). */
    public function unallocatedCredit(): float
    {
        $received = (float) $this->payments()->settled()->sum('amount');
        $allocated = (float) PaymentAllocation::whereHas('payment', fn ($q) => $q
            ->where('customer_id', $this->id)
            ->settled())->sum('amount');
        return round($received - $allocated, 2);
    }

    public function visits()
    {
        return $this->hasMany(RepVisit::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }


    public function availableCredit(): ?float
    {
        if ((float) $this->credit_limit <= 0) {
            return null;
        }

        return (float) $this->credit_limit - $this->outstandingBalance();
    }
}
