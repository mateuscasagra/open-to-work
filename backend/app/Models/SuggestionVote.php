<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestionVote extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'suggestion_id',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }
}
