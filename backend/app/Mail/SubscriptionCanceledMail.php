<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de confirmação de cancelamento, enviado pela nossa stack (Resend).
 * O acesso Pro segue até o fim do período já pago — daí o $accessUntil.
 */
final class SubscriptionCanceledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly ?string $accessUntil,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('subscription.mail.canceled.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.subscription-canceled');
    }
}
