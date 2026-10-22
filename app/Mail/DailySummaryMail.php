<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Morning summary for managers: what needs attention today. */
class DailySummaryMail extends Mailable
{
    public function __construct(public array $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Daily summary '.today()->format('d/m/Y').' – '.config('distributor.company.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.daily-summary');
    }
}
