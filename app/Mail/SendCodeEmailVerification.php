<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendCodeEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address - Medihive',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.verification-code', // Make sure this matches your template
            with: ['code' => $this->code] // Explicitly pass the code
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
