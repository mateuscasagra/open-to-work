<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatus: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Assessment = 'assessment';
    case InterviewHR = 'interview_hr';
    case InterviewTech = 'interview_tech';
    case Offer = 'offer';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Aplicada',
            self::Screening => 'Triagem',
            self::Assessment => 'Teste',
            self::InterviewHR => 'Entrevista RH',
            self::InterviewTech => 'Entrevista Técnica',
            self::Offer => 'Proposta',
            self::Accepted => 'Aceita',
            self::Rejected => 'Recusada',
            self::Withdrawn => 'Desistência',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Withdrawn], true);
    }
}
