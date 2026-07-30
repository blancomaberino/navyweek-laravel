<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Listeners;

use App\Domain\Publishing\Events\PageUrlChanged;
use App\Domain\Publishing\Repositories\RedirectRepositoryInterface;

/**
 * Turns a `PageUrlChanged` into a live 301 — the auto-redirect half of the "editable
 * URLs, zero deploys" requirement. The `CanonicalUrlMiddleware` already consults the
 * `redirects` store (step 5b), so a row written here is honored on the very next
 * request with no build.
 *
 * The redirect-graph rewrite (drop stale target, collapse inbound chains to a single
 * hop, upsert the slug-change rule, clear self-redirects — all scoped to EXACT rules)
 * lives in `RedirectRepositoryInterface::recordSlugChange`; this listener only
 * translates the domain event into that call.
 */
final class CreateRedirectListener
{
    public function __construct(
        private readonly RedirectRepositoryInterface $redirects,
    ) {}

    public function handle(PageUrlChanged $event): void
    {
        $this->redirects->recordSlugChange($event->oldPath, $event->newPath);
    }
}
