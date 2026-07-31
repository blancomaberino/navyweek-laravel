<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Events;

use App\Domain\Publishing\Models\Page;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a published page's canonical `url_path` changes (an editor renamed it
 * in the admin panel). Carries the old + new paths so listeners can create the
 * 301 (CreateRedirectListener) and, later, invalidate the response cache (Phase 6).
 */
final class PageUrlChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Page $page,
        public readonly string $oldPath,
        public readonly string $newPath,
    ) {}
}
