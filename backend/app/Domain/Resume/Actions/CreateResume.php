<?php

declare(strict_types=1);

namespace App\Domain\Resume\Actions;

use App\Domain\Resume\DTOs\ResumeData;
use App\Domain\Resume\DTOs\ResumeSectionData;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

final class CreateResume
{
    public function execute(User $user, ResumeData $data): Resume
    {
        return DB::transaction(function () use ($user, $data): Resume {
            $resume = Resume::query()->create([
                'user_id' => $user->id,
                'title' => $data->title instanceof Optional ? '' : $data->title,
                'language' => $data->language instanceof Optional ? 'pt_BR' : $data->language,
                'is_pdf_upload' => false,
            ]);

            if (! ($data->sections instanceof Optional)) {
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
