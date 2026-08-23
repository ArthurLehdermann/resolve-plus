<?php

namespace App\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nome,
        public readonly string $codigo,
        public readonly int $expiraEmMinutos,
    ) {}

    /**
     * O código vai no assunto porque é ali que ele resolve o problema de quem
     * está com o app aberto na outra mão: dá para ler na notificação e digitar
     * sem abrir o e-mail. É o que Google, Apple e Stripe fazem com código de
     * uso único.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->codigo} é o seu código de acesso ao Resolve+",
        );
    }

    /**
     * Sempre as duas versões: a HTML é a que quase todo mundo vê, e a de texto
     * cobre leitor de tela, cliente antigo e filtro de spam — que pontua pior
     * quem manda só HTML.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.magic-link',
            text: 'emails.auth.magic-link-text',
            with: [
                // "Olá, Arthur" soa como gente; "Olá, Arthur Lehdermann da
                // Silva" soa como cadastro.
                'primeiroNome' => Str::of($this->nome)->trim()->before(' ')->toString(),
            ],
        );
    }
}
