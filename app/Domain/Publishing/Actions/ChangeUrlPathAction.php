<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Events\PageUrlChanged;
use App\Domain\Publishing\Models\Page;
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
    public function __invoke(Page $page, string $newUrlPath): void
    {
        $oldUrlPath = $page->url_path;

        if ($oldUrlPath === $newUrlPath) {
            return;
        }

        DB::transaction(function () use ($page, $oldUrlPath, $newUrlPath): void {
            $page->url_path = $newUrlPath;
            $page->save();

            // Synchronous listener → the redirect bookkeeping runs inside this txn.
            PageUrlChanged::dispatch($page, $oldUrlPath, $newUrlPath);
        });
    }
}
