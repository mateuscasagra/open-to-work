<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ApplicationFollowUpNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Application $application) {}

    /**
     * @return array<int, string>
     */
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        $jobTitle = $this->application->job?->title ?? '—';
        $company = $this->application->job?->company?->name ?? '—';
        $daysSince = (int) $this->application->applied_at->diffInDays(now());

        return (new MailMessage)
            ->subject(__('Follow-up: :title em :company', ['title' => $jobTitle, 'company' => $company]))
            ->greeting(__('Olá, :name', ['name' => $this->application->user?->name ?? '']))
            ->line(__('Já faz :n dias desde que você se candidatou a :title na :company sem atualização.', [
                'n' => $daysSince,
                'title' => $jobTitle,
                'company' => $company,
            ]))
            ->line(__('Que tal enviar uma mensagem curta ao recrutador ou registrar uma nova etapa?'))
            ->action(__('Abrir candidatura'), url('/applications/' . $this->application->id));
    }
}
