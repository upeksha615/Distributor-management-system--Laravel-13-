<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'returned_quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Net price per unit after the line discount. */
    public function netUnitPrice(): float
    {
        return round((float) $this->unit_price * (1 - (float) $this->discount_percent / 100), 2);
    }

    public function returnableQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->returned_quantity);
    }
}
