<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'cheque_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function methodLabel(): string
    {
        return Payment::METHODS[$this->method] ?? $this->method;
    }

    public function unallocatedAmount(): float
    {
        return round((float) $this->amount - (float) $this->allocations()->sum('amount'), 2);
    }
}
