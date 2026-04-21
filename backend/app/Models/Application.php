<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Application extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    public const ATTACHMENT_COLLECTION = 'attachments';
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024; // 10 MB

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENT_COLLECTION);
    }

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'job_id',
        'resume_id',
        'status',
        'applied_at',
        'source',
        'notes',
        'expected_salary',
        'sent_via_email_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'applied_at' => 'datetime',
            'sent_via_email_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class);
    }
}
