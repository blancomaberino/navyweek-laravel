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

        // Only EXACT rules are ever touched here. A PREFIX rule that shares a path is
        // an admin-managed rule for that path's *descendants* — it can never redirect
        // the now-live exact page itself, so it must be left intact.
        $exact = RedirectMatchType::Exact;

        // The new path is now a live exact page — drop any stale EXACT rule pointing
        // FROM it (including a reverse `/new/ → /old/` from an earlier rename).
        Redirect::query()->where('from_path', $new)->where('match_type', $exact)->delete();

        // Collapse inbound EXACT chains: whatever used to 301 to the old path now
        // lands on the new one directly (prefix rules pointing at old are preserved).
        Redirect::query()->where('to_path', $old)->where('match_type', $exact)->update(['to_path' => $new]);

        // The old path 301s to the new one — an EXACT slug-change rule. Keying the
        // upsert on (from_path, match_type=exact) means a prefix rule at `$old` is
        // never matched and overwritten.
        Redirect::query()->updateOrCreate(
            ['from_path' => $old, 'match_type' => $exact],
            [
                'to_path' => $new,
                'status' => 301,
                'reason' => 'slug-change',
                'is_active' => true,
            ],
        );

        // Never leave an EXACT self-redirect behind (a self-301 is a loop).
        Redirect::query()->whereColumn('from_path', 'to_path')->where('match_type', $exact)->delete();
    }
}
