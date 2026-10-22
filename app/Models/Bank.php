<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    public const TYPES = ['commercial' => 'Licensed commercial bank', 'specialised' => 'Licensed specialised bank', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function branches()
    {
        return $this->hasMany(BankBranch::class);
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->name;
    }
}
