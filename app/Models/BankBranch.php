<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankBranch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
