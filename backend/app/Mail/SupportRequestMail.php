<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SupportRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Nomes evitam colisão com propriedades não-readonly da classe pai Mailable
     * (`$subject`, `$body`, etc. são reservadas — não dá pra redeclarar readonly).
     */
    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $subjectLine,
        public readonly string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Suporte Open to Work] ' . $this->subjectLine,
            // replyTo permite responder direto pro usuário sem mostrar
            // o e-mail interno de envio (no-reply / smtp).
            replyTo: [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support-request');
    }
}
