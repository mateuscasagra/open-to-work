<?php

declare(strict_types=1);

namespace App\Domain\Admin\Queries;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Distribuição geográfica dos usuários cadastrados, agregada para o
 * painel admin. Usa apenas registros de `profiles` com `country_code`
 * preenchido; usuários sem perfil ou sem país entram em `without_location`.
 */
final class GetUserLocationDistribution
{
    public const TOP_STATES_LIMIT = 10;

    public const TOP_CITIES_LIMIT = 15;

    /**
     * @return array{
     *     countries: list<array{country_code: string, count: int}>,
     *     states: list<array{country_code: string, state_code: ?string, state_name: ?string, count: int}>,
     *     cities: list<array{country_code: string, city: string, count: int}>,
     *     without_location: int
     * }
     */
    public function execute(): array
    {
        return [
            'countries' => $this->countries(),
            'states' => $this->states(),
            'cities' => $this->cities(),
            'without_location' => $this->withoutLocation(),
        ];
    }

    /**
     * @return list<array{country_code: string, count: int}>
     */
    private function countries(): array
    {
        $rows = DB::table('profiles')
            ->select('country_code')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->orderBy('country_code')
            ->get();

        return $rows->map(fn ($row): array => [
            'country_code' => (string) $row->country_code,
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * @return list<array{country_code: string, state_code: ?string, state_name: ?string, count: int}>
     */
    private function states(): array
    {
        $rows = DB::table('profiles')
            ->select('country_code', 'state_code', 'state_name')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('country_code')
            ->whereNotNull('state_name')
            ->groupBy('country_code', 'state_code', 'state_name')
            ->orderByDesc('count')
            ->orderBy('country_code')
            ->orderBy('state_name')
            ->limit(self::TOP_STATES_LIMIT)
            ->get();

        return $rows->map(fn ($row): array => [
            'country_code' => (string) $row->country_code,
            'state_code' => $row->state_code !== null ? (string) $row->state_code : null,
            'state_name' => $row->state_name !== null ? (string) $row->state_name : null,
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * @return list<array{country_code: string, city: string, count: int}>
     */
    private function cities(): array
    {
        $rows = DB::table('profiles')
            ->select('country_code', 'city')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('country_code')
            ->whereNotNull('city')
            ->groupBy('country_code', 'city')
            ->orderByDesc('count')
            ->orderBy('country_code')
            ->orderBy('city')
            ->limit(self::TOP_CITIES_LIMIT)
            ->get();

        return $rows->map(fn ($row): array => [
            'country_code' => (string) $row->country_code,
            'city' => (string) $row->city,
            'count' => (int) $row->count,
        ])->all();
    }

    private function withoutLocation(): int
    {
        return User::query()
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->whereNull('profiles.country_code')
            ->count();
    }
}
