<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('records daily metrics per user', function (): void {
    $user = User::factory()->create();

    DB::table('metrics_daily')->insert([
        'user_id' => $user->id,
        'date' => '2026-04-17',
        'applications_count' => 5,
        'responses_count' => 2,
        'interviews_count' => 1,
        'offers_count' => 0,
        'rejections_count' => 1,
        'breakdown' => json_encode(['channels' => ['linkedin' => 3, 'indeed' => 2]]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('metrics_daily')->where('user_id', $user->id)->first();

    expect($row->applications_count)->toBe(5);
    $breakdown = json_decode($row->breakdown, true);
    expect($breakdown['channels']['linkedin'])->toBe(3);
});

it('enforces unique user+date', function (): void {
    $user = User::factory()->create();

    DB::table('metrics_daily')->insert([
        'user_id' => $user->id,
        'date' => '2026-04-17',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('metrics_daily')->insert([
        'user_id' => $user->id,
        'date' => '2026-04-17',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
