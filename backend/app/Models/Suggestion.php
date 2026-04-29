<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Suggestion extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'upvotes_count',
        'downvotes_count',
        'score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'upvotes_count' => 'integer',
            'downvotes_count' => 'integer',
            'score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(SuggestionVote::class);
    }
}
