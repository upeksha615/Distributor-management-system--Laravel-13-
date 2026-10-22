<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A brand belongs to one category; products belong to a brand. */
class Brand extends Model
{
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
