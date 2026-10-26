<?php

namespace App\Auth\Mail;

use App\Auth\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso interno para a BigWorks: entrou uma conta nova no Resolve+. */
class NovoUsuarioAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Usuario $usuario,
        public readonly string $origem,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Novo cadastro no Resolve+: {$this->usuario->nome} ({$this->tipoLegivel()})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.novo-usuario',
            text: 'emails.admin.novo-usuario-text',
            with: [
                'tipo' => $this->tipoLegivel(),
                'cadastradoEm' => $this->usuario->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            ],
        );
    }

    private function tipoLegivel(): string
    {
        return mb_strtolower($this->usuario->tipo->name);
    }
}
