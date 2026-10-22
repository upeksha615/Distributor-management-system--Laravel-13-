<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An upcoming shipment. Its quantities are NOT stock until the shipment is received. */
class Shipment extends Model
{
    public const STATUSES = ['pending' => 'Pending (on the way)', 'partial' => 'Partly received', 'received' => 'Received', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected $casts = ['expected_date' => 'date', 'received_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(ShipmentItem::class);
    }

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

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'partial'], true);
    }
}
