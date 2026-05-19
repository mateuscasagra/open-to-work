<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Modality;
use App\Enums\Seniority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Job extends Model
{
    use HasFactory, Searchable;

    /** @var list<string> */
    protected $fillable = [
        'canonical_hash',
        'title',
        'company_id',
        'description_html',
        'location',
        'country_code',
        'modality',
        'seniority',
        'stack',
        'salary_min',
        'salary_max',
        'salary_currency',
        'language',
        'contact_email',
        'posted_at',
        'expires_at',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stack' => 'array',
            'modality' => Modality::class,
            'seniority' => Seniority::class,
            'posted_at' => 'datetime',
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<JobSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(JobSource::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'location' => $this->location,
            'modality' => $this->modality?->value,
            'seniority' => $this->seniority?->value,
            'stack' => $this->stack,
            'language' => $this->language,
            'posted_at' => $this->posted_at?->timestamp,
        ];
    }
}
