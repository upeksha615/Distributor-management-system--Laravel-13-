<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFitment extends Model
{
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** e.g. "Toyota Axio 2012-2018 (1NZ-FE)" */
    public function label(): string
    {
        $years = match (true) {
            $this->year_from && $this->year_to => "{$this->year_from}-{$this->year_to}",
            (bool) $this->year_from => "{$this->year_from}+",
            (bool) $this->year_to => "to {$this->year_to}",
            default => '',
        };

        return trim("{$this->make} {$this->model} {$years}".($this->engine ? " ({$this->engine})" : ''));
    }
}
