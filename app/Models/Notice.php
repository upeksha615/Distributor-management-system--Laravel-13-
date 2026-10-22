<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    public const LEVELS = ['info' => 'Information', 'important' => 'Important'];

    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Notices showing today. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereDate('starts_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    public function isCurrent(): bool
    {
        return $this->starts_on->lte(today()) && ($this->ends_on === null || $this->ends_on->gte(today()));
    }
}
