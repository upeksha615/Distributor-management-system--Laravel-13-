<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** Weekly reminder to a dealer listing invoices past their due date. */
class OverdueReminderMail extends Mailable
{
    use CompanyEnvelope;

    public function __construct(public Customer $customer, public Collection $invoices, public float $total, public float $pending) {}

    public function envelope(): Envelope
    {
        return $this->companyEnvelope('Payment reminder – overdue invoices – '.config('distributor.company.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.overdue-reminder');
    }
}
