<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RepVisit extends Model
{
    public const OUTCOMES = [
        'order' => 'Order taken',
        'payment' => 'Payment collected',
        'order_payment' => 'Order + payment',
        'no_order' => 'No order',
        'follow_up' => 'Follow up needed',
        'closed' => 'Shop closed / not available',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'next_visit_date' => 'date',
    ];

    public function rep()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('checked_out_at');
    }

    public static function activeFor(User $user): ?self
    {
        return static::where('user_id', $user->id)->whereNull('checked_out_at')->latest('id')->first();
    }

    public function isActive(): bool
    {
        return $this->checked_out_at === null;
    }

    /** Minutes at the dealer (so far, if still checked in). */
    public function minutes(): int
    {
        return (int) $this->checked_in_at->diffInMinutes($this->checked_out_at ?? now());
    }

    public function outcomeLabel(): ?string
    {
        return $this->outcome ? (self::OUTCOMES[$this->outcome] ?? $this->outcome) : null;
    }

    public function mapUrl(): ?string
    {
        return $this->check_in_lat ? "https://www.google.com/maps?q={$this->check_in_lat},{$this->check_in_lng}" : null;
    }

    /** Distance in metres between two GPS points (haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
