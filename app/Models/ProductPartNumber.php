<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPartNumber extends Model
{
    public const TYPES = ['oem' => 'OEM', 'alternate' => 'Alternate', 'supplier' => 'Supplier'];

    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
