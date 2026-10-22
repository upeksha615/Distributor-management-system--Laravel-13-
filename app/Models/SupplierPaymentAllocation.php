<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPaymentAllocation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment()
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }
}
