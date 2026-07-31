<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Events\PageUrlChanged;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Shared\ValueObjects\UrlPath;
use Illuminate\Support\Facades\DB;

/**
 * The intentional "rename a page's URL" operation — the editor half of editable URLs.
 * Moves the page to its new canonical `url_path` and fires `PageUrlChanged` so the
 * 301 is created (CreateRedirectListener), all in one transaction. A no-op when the
 * path is unchanged, so it is safe to call unconditionally from the Filament save.
 *
 * The unique constraint on `pages.url_path` guarantees a target collision fails loud
 * (the Filament form validates it first); this action does not silently overwrite.
 */
final class ChangeUrlPathAction
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function __invoke(Page $page, string $newUrlPath): void
    {
        // Canonicalize at the boundary so the stored `url_path` AND the derived
        // redirect (`from_path`/`to_path`) are the exact form the middleware matches
        // (it lowercases + trailing-slashes). A non-canonical input would otherwise
        // store a page the router can't serve and a redirect that never fires.
        $newUrlPath = UrlPath::from($newUrlPath)->value();

        DB::transaction(function () use ($page, $newUrlPath): void {
            // Lock + reload the current row so concurrent renames from the same
            // original path serialize: A→B and A→C can't both derive their redirect
            // from a stale in-memory "A" and leave one of B/C without a redirect.
            $locked = $this->pages->findForUpdate($page);
            if ($locked === null) {
                return; // deleted concurrently
            }

            $oldUrlPath = $locked->url_path;
            if ($oldUrlPath === $newUrlPath) {
                return;
            }

            $this->pages->updateUrlPath($locked, $newUrlPath);

            // Keep the caller's instance in sync with the persisted change.
            $page->setRawAttributes($locked->getAttributes(), sync: true);

            // Synchronous listener → the redirect bookkeeping runs inside this txn,
            // keyed on the locked current path (not the caller's snapshot).
            PageUrlChanged::dispatch($locked, $oldUrlPath, $newUrlPath);
        });
    }
}
