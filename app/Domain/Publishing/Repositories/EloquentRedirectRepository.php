<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Publishing\Enums\RedirectMatchType;
use App\Domain\Publishing\Models\Redirect;

final class EloquentRedirectRepository implements RedirectRepositoryInterface
{
    public function matchFor(string $path): ?Redirect
    {
        // Exact match wins outright.
        $exact = Redirect::query()
            ->where('is_active', true)
            ->where('match_type', RedirectMatchType::Exact)
            ->where('from_path', $path)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        // Otherwise the longest matching prefix (most specific rule). Prefix rules
        // are a small, curated set (the algorithmic collapses), so matching them
        // in PHP is both correct and driver-independent — no LIKE escaping.
        // A prefix rule matches strict descendants only: the path must start with
        // `from_path` but not equal it — so a collapse rule like /navy-ranks/ →
        // /navy-ranks/ redirects its subpaths without self-redirecting the list
        // page (mirrors middleware.ts `startsWith(prefix) && path !== prefix`).
        return Redirect::query()
            ->where('is_active', true)
            ->where('match_type', RedirectMatchType::Prefix)
            ->orderByRaw('LENGTH(from_path) DESC')
            ->orderBy('from_path')
            ->get()
            ->first(static fn (Redirect $r): bool => $path !== $r->from_path && str_starts_with($path, $r->from_path));
    }

    public function incrementHits(Redirect $redirect): void
    {
        $redirect->increment('hits');
    }
}
