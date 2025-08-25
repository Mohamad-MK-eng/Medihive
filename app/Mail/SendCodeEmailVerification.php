<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendCodeEmailVerification extends Mailable implements ShouldQueue
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
            markdown: 'emails.send-code-email-verification', // Match the template name
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
