<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricsDaily extends Model
{
    protected $table = 'metrics_daily';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'date',
        'applications_count',
        'responses_count',
        'interviews_count',
        'offers_count',
        'rejections_count',
        'breakdown',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // intentionally not casting `date` → store as plain 'Y-m-d' string
            // (avoids SQLite quirk where the `date` cast serializes to 'Y-m-d 00:00:00'
            // and breaks `updateOrCreate` lookups).
            'applications_count' => 'integer',
            'responses_count' => 'integer',
            'interviews_count' => 'integer',
            'offers_count' => 'integer',
            'rejections_count' => 'integer',
            'breakdown' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
