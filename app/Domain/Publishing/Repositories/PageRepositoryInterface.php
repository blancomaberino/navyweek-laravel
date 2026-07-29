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
     * Connection ids that own a published discount-brand page (a "live" brand) —
     * the reconcile gate's notion of "published".
     *
     * @return array<int, int>
     */
    public function connectionIdsWithPublishedDiscountBrandPage(): array;
}
