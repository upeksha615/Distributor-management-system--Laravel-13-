<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

/** Subject plus a reply-to of the company email (Settings) so dealer replies reach the office. */
trait CompanyEnvelope
{
    protected function companyEnvelope(string $subject): Envelope
    {
        $replyTo = config('distributor.company.email');

        return new Envelope(
            subject: $subject,
            replyTo: filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? [new Address($replyTo, config('distributor.company.name'))] : [],
        );
    }
}
