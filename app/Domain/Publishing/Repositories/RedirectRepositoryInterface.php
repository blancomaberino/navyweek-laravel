<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Publishing\Models\Redirect;

interface RedirectRepositoryInterface
{
    /**
     * The active redirect that applies to a normalized request path: an exact
     * match wins over any prefix match; among prefix matches the longest
     * `from_path` wins (most specific rule). Null when nothing matches.
     */
    public function matchFor(string $path): ?Redirect;

    public function incrementHits(Redirect $redirect): void;

    /**
     * Rewrite the EXACT-redirect graph for a page rename (`$oldPath` → `$newPath`):
     * drop stale exact rules pointing at the now-live new path, collapse inbound
     * exact chains onto it, upsert the `slug-change` 301 from the old path, and
     * clear any exact self-redirect. PREFIX rules are never touched — an
     * admin-managed prefix rule sharing a path targets that path's descendants and
     * can never redirect the live exact page itself. A no-op when the paths match.
     */
    public function recordSlugChange(string $oldPath, string $newPath): void;
}
