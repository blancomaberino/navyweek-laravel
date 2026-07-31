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

    public function recordSlugChange(string $oldPath, string $newPath): void
    {
        if ($oldPath === $newPath) {
            return;
        }

        // Only EXACT rules are ever touched here. A PREFIX rule that shares a path is
        // an admin-managed rule for that path's *descendants* — it can never redirect
        // the now-live exact page itself, so it must be left intact.
        $exact = RedirectMatchType::Exact;

        // The new path is now a live exact page — drop any stale EXACT rule pointing
        // FROM it (including a reverse `/new/ → /old/` from an earlier rename).
        Redirect::query()->where('from_path', $newPath)->where('match_type', $exact)->delete();

        // Collapse inbound EXACT chains: whatever used to 301 to the old path now
        // lands on the new one directly (prefix rules pointing at old are preserved).
        Redirect::query()->where('to_path', $oldPath)->where('match_type', $exact)->update(['to_path' => $newPath]);

        // The old path 301s to the new one — an EXACT slug-change rule. Keying the
        // upsert on (from_path, match_type=exact) means a prefix rule at `$oldPath`
        // is never matched and overwritten.
        Redirect::query()->updateOrCreate(
            ['from_path' => $oldPath, 'match_type' => $exact],
            [
                'to_path' => $newPath,
                'status' => 301,
                'reason' => 'slug-change',
                'is_active' => true,
            ],
        );

        // Never leave an EXACT self-redirect behind (a self-301 is a loop).
        Redirect::query()->whereColumn('from_path', 'to_path')->where('match_type', $exact)->delete();
    }
}
