<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailApplyMode;
use App\Enums\Modality;
use App\Enums\Seniority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profile extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'desired_role',
        'seniority',
        'modality',
        'salary_min',
        'salary_max',
        'salary_currency',
        'location',
        'languages',
        'bio',
        'email_apply_enabled',
        'email_apply_message_mode',
        'email_apply_message_template',
        'email_apply_resume_mode',
        'email_apply_resume_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seniority' => Seniority::class,
            'modality' => Modality::class,
            'languages' => 'array',
            'email_apply_enabled' => 'boolean',
            'email_apply_message_mode' => EmailApplyMode::class,
            'email_apply_resume_mode' => EmailApplyMode::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'profile_skills')
            ->withPivot('proficiency')
            ->withTimestamps();
    }

    public function emailApplyResume(): BelongsTo
    {
        return $this->belongsTo(Resume::class, 'email_apply_resume_id');
    }
}
