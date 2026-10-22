<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public const TYPES = [
        'password_reset' => 'Password reset',
        'invoice' => 'Invoice',
        'payment_verified' => 'Payment verified',
        'payment_rejected' => 'Payment rejected',
        'cheque_returned' => 'Cheque returned',
        'overdue_reminder' => 'Overdue reminder',
        'daily_summary' => 'Daily summary',
        'test' => 'Test email',
    ];

    public const STATUSES = [
        'pending' => 'Sending',
        'sent' => 'Sent',
        'logged' => 'Not delivered (log mailer)',
        'failed' => 'Failed',
    ];

    protected $guarded = ['id'];

    protected $casts = ['sent_at' => 'datetime'];

    public function related()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statusClass(): string
    {
        return [
            'sent' => 'bg-green-100 text-green-800',
            'logged' => 'bg-amber-100 text-amber-800',
            'failed' => 'bg-red-100 text-red-800',
        ][$this->status] ?? 'bg-slate-100 text-slate-700';
    }
}
