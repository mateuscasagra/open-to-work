<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de boas-vindas ao Pro, enviado pela nossa stack (Resend) quando a
 * 1ª cobrança é confirmada. As notificações do próprio Asaas ficam desligadas
 * por cliente (notificationDisabled) — ver AsaasHttpClient::createCustomer.
 */
final class SubscriptionConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $renewsAt,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('subscription.mail.confirmed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.subscription-confirmed');
    }
}
