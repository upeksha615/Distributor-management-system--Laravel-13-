<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'is_bank' => 'boolean', 'is_public' => 'boolean', 'is_mercantile' => 'boolean'];

    public function isPoya(): bool
    {
        return str_contains($this->name, 'Poya');
    }

    /** e.g. "Bank · Public · Mercantile" */
    public function typesLabel(): string
    {
        return collect(['Bank' => $this->is_bank, 'Public' => $this->is_public, 'Mercantile' => $this->is_mercantile])->filter()->keys()->join(' · ');
    }
}
