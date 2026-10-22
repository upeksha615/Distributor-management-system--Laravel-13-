<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Top level of the catalogue: Category → Brand → Product. */
class Category extends Model
{
    protected $guarded = ['id'];

    public function brands()
    {
        return $this->hasMany(Brand::class)->orderBy('name');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
