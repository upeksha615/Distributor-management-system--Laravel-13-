<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'payment_status_history';

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
