<?php

declare(strict_types=1);

namespace App\Domain\Resume\Actions;

use App\Domain\Resume\DTOs\ResumeData;
use App\Domain\Resume\DTOs\ResumeSectionData;
use App\Models\Resume;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

final class UpdateResume
{
    public function execute(Resume $resume, ResumeData $data): Resume
    {
        return DB::transaction(function () use ($resume, $data): Resume {
            $attrs = [];
            if (! ($data->title instanceof Optional)) {
                $attrs['title'] = $data->title;
            }
            if (! ($data->language instanceof Optional)) {
                $attrs['language'] = $data->language;
            }
            if ($attrs !== []) {
                $resume->update($attrs);
            }

            if (! ($data->sections instanceof Optional)) {
                $resume->sections()->delete();
                /** @var ResumeSectionData $section */
                foreach ($data->sections as $section) {
                    $resume->sections()->create([
                        'type' => $section->type,
                        'order' => $section->order,
                        'content' => $section->content,
                    ]);
                }
            }

            return $resume->load('sections');
        });
    }
}
