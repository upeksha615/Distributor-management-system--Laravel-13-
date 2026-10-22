<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupplierBill extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }

    public function balance(): float
    {
        return round((float) $this->amount - (float) $this->paid_amount, 2);
    }

    public function daysOverdue(): int
    {
        return $this->due_date->isPast() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }
        $this->paid_amount = $this->allocations()->sum('amount');
        $this->status = match (true) {
            $this->balance() <= 0 => 'paid',
            (float) $this->paid_amount > 0 => 'partial',
            default => 'unpaid',
        };
        $this->save();
    }
}
