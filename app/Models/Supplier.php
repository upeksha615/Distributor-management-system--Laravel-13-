<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function bills()
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** What we owe: open bills less payments not yet applied to a bill. */
    public function balance(): float
    {
        $bills = (float) $this->bills()->where('status', '!=', 'cancelled')->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as b')->value('b');
        $paid = (float) $this->payments()->sum('amount');
        $allocated = (float) SupplierPaymentAllocation::whereHas('payment', fn ($q) => $q->where('supplier_id', $this->id))->sum('amount');

        return round($bills - ($paid - $allocated), 2);
    }
}
