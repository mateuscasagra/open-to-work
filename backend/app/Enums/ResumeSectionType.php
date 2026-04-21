<?php

declare(strict_types=1);

namespace App\Enums;

enum ResumeSectionType: string
{
    case Summary = 'summary';
    case Experience = 'experience';
    case Education = 'education';
    case Skill = 'skill';
    case Language = 'language';
    case Project = 'project';
    case Contact = 'contact';
}
