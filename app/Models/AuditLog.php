<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    /** Record a sensitive action, e.g. AuditLog::record('invoice.cancel', $invoice, 'Cancelled INV-...'). */
    public static function record(string $action, ?Model $subject, string $description): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
