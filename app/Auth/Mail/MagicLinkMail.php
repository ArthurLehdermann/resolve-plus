<?php

namespace App\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $codigo,
        public readonly int $expiraEmMinutos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seu código de acesso ao Resolve+',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.auth.magic-link',
        );
    }
}
