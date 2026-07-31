<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Publishing\Models\Page;

interface PageRepositoryInterface
{
    /**
     * Whether a published page owns this exact canonical `url_path`. The DB
     * successor to the legacy build-time route manifest (`VALID_ROUTES`): a hit
     * means the request is a live route and passes through; a miss falls to the
     * catch-all → "/".
     */
    public function publishedPathExists(string $urlPath): bool;

    /**
     * The published page at this exact canonical `url_path`, with its `pageable`
     * aggregate eager-loaded, or null. The render read (Phase 3) — everything the
     * head/JSON-LD builders need in one query. Non-canonical or unpublished paths
     * return null (the middleware has already 301'd those).
     */
    public function findPublishedByPath(string $urlPath): ?Page;

    /**
     * Re-read the given page's row under a `FOR UPDATE` row lock, so a rename can
     * serialize against concurrent writers. Must be called inside a transaction;
     * returns null if the row was deleted concurrently.
     */
    public function findForUpdate(Page $page): ?Page;

    /**
     * Persist a new canonical `url_path` on an already-loaded page.
     */
    public function updateUrlPath(Page $page, string $newUrlPath): void;
}
