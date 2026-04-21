<?php

declare(strict_types=1);

namespace App\Domain\Resume\DTOs;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Optional;

final class ResumeData extends Data
{
    /**
     * @param  DataCollection<int, ResumeSectionData>|Optional  $sections
     */
    public function __construct(
        public string|Optional $title,
        public string|Optional $language,
        #[DataCollectionOf(ResumeSectionData::class)]
        public DataCollection|Optional $sections,
    ) {}
}
