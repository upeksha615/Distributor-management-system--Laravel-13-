<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the dealer: payment verified / rejected, or cheque returned by the bank. */
class PaymentNoticeMail extends Mailable
{
    use CompanyEnvelope;

    public function __construct(public Payment $payment, public string $kind) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->kind) {
            'verified' => $this->payment->method === 'cheque' && ! $this->payment->isSettled()
                ? "Cheque received – {$this->payment->number} (subject to clearance)"
                : "Payment received – {$this->payment->number}",
            'rejected' => "Payment could not be confirmed – {$this->payment->number}",
            'returned' => "Cheque returned by the bank – {$this->payment->number}",
        };

        return $this->companyEnvelope($subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.payment-notice');
    }
}
