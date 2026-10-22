<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email – '.config('distributor.company.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.test');
    }
}
