<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Listeners;

use App\Domain\Publishing\Enums\RedirectMatchType;
use App\Domain\Publishing\Events\PageUrlChanged;
use App\Domain\Publishing\Models\Redirect;

/**
 * Turns a `PageUrlChanged` into a live 301 — the auto-redirect half of the "editable
 * URLs, zero deploys" requirement. The `CanonicalUrlMiddleware` already consults the
 * `redirects` store (step 5b), so a row written here is honored on the very next
 * request with no build.
 *
 * Chain collapse keeps every redirect a single hop: when `/a/ → /old/` already exists
 * and `/old/` is renamed to `/new/`, `/a/` is repointed straight to `/new/` instead
 * of leaving a `/a/ → /old/ → /new/` two-hop.
 */
final class CreateRedirectListener
{
    public function handle(PageUrlChanged $event): void
    {
        $old = $event->oldPath;
        $new = $event->newPath;

        if ($old === $new) {
            return;
        }

        // The new path is now a live page — it must never 301 away. Drop any stale
        // rule pointing FROM it (including a reverse `/new/ → /old/` from an earlier
        // rename that we're about to undo).
        Redirect::query()->where('from_path', $new)->delete();

        // Collapse inbound chains: whatever used to land on the old path now lands
        // on the new one directly.
        Redirect::query()->where('to_path', $old)->update(['to_path' => $new]);

        // The old path 301s to the new one (idempotent on re-rename back and forth).
        Redirect::query()->updateOrCreate(
            ['from_path' => $old],
            [
                'to_path' => $new,
                'status' => 301,
                'reason' => 'slug-change',
                'match_type' => RedirectMatchType::Exact,
                'is_active' => true,
            ],
        );

        // Never leave a self-redirect behind (a collapse can only produce one if the
        // data was already inconsistent, but guard anyway — a self-301 is a loop).
        Redirect::query()->whereColumn('from_path', 'to_path')->delete();
    }
}
