<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    public const STATUSES = ['draft', 'ordered', 'partial', 'received', 'cancelled'];

    protected $guarded = ['id'];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['ordered', 'partial']);
    }
}
