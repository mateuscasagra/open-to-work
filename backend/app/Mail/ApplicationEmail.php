<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $body,
        public readonly string $candidateName,
        public readonly string $jobTitle,
        public readonly ?string $resumePath = null,
        public readonly ?string $resumeTitle = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Candidatura: {$this->jobTitle} — {$this->candidateName}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.application');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->resumePath) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('s3', $this->resumePath)
                ->as(($this->resumeTitle ?? 'resume') . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
