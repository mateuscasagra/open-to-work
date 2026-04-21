<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SupportedLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if ($locale !== null) {
            app()->setLocale($locale->value);
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): ?SupportedLocale
    {
        $user = $request->user();
        if ($user !== null) {
            $userLocale = $user->locale;
            if ($userLocale instanceof SupportedLocale) {
                return $userLocale;
            }
            if (is_string($userLocale) && $userLocale !== '') {
                $match = SupportedLocale::tryFrom($userLocale);
                if ($match !== null) {
                    return $match;
                }
            }
        }

        foreach ($request->getLanguages() as $lang) {
            $normalized = str_replace('-', '_', (string) $lang);
            $match = SupportedLocale::tryFrom($normalized)
                ?? $this->matchByPrefix($normalized);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    private function matchByPrefix(string $lang): ?SupportedLocale
    {
        $prefix = strtolower(explode('_', $lang, 2)[0]);

        return match ($prefix) {
            'pt' => SupportedLocale::PtBr,
            'en' => SupportedLocale::En,
            'es' => SupportedLocale::Es,
            default => null,
        };
    }
}
