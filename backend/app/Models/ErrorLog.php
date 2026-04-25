<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $level
 * @property string $exception_class
 * @property string $message
 * @property string|null $file
 * @property int|null $line
 * @property string|null $stack_trace
 * @property array<string, mixed>|null $context
 * @property string|null $url
 * @property string|null $method
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User|null $user
 */
final class ErrorLog extends Model
{
    protected $fillable = [
        'level',
        'exception_class',
        'message',
        'file',
        'line',
        'stack_trace',
        'context',
        'url',
        'method',
        'user_id',
        'occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
        'line' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
