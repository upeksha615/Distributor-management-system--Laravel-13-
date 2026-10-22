<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Invoice PDF to the dealer. */
class InvoiceMail extends Mailable
{
    use CompanyEnvelope;

    public function __construct(public Invoice $invoice, public string $pdf, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        return $this->companyEnvelope("Invoice {$this->invoice->number} from ".config('distributor.company.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invoice');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, "Invoice-{$this->invoice->number}.pdf")->withMime('application/pdf')];
    }
}
