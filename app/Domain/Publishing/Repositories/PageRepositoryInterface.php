<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Publishing\Models\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface PageRepositoryInterface
{
    /**
     * Idempotently create-or-update the `pages` row for a generated page (base, rank,
     * event, hub, …), keyed on the stable `$generationKey` (NOT the url_path), pointing
     * `pageable` at the given model. Location is separate from identity:
     *
     *  - A new page is created at `$defaultUrlPath`.
     *  - An editor-renamed page (`url_path_is_custom`) keeps its path across regeneration.
     *  - Otherwise the page tracks `$defaultUrlPath`; when a family prefix changes
     *    (config('publishing.paths.*')) the page moves and a 301 from the old path is
     *    created automatically (PageUrlChanged → CreateRedirectListener).
     *
     * Honors the build clock: `date_published` is set only when the row is first created
     * and preserved verbatim on every later run; every call refreshes `date_modified`.
     * Returns the persisted page.
     *
     * @param  string  $generationKey  Stable per-page identity assigned by the generator
     *                                 (e.g. "base:norfolk", "local-hub:root"). Build it
     *                                 from stable ids/slugs, never from the url_path.
     * @param  string  $defaultUrlPath  The family-default path (built via PagePaths), used
     *                                  as the create-time path and the move target.
     * @param  array<string, mixed>  $attributes  Page columns (title, meta_description,
     *                                            og_image_path, page_type, slug, dates, …).
     * @param  Model|null  $pageable  The aggregate the page presents, or null for a
     *                                list/hub page that owns no single aggregate
     *                                (e.g. the /navy-ranks/ and /navy-ratings/ lists).
     */
    public function upsertPillarPage(string $generationKey, string $defaultUrlPath, array $attributes, ?Model $pageable = null): Page;

    /**
     * The generated page with this stable `generation_key`, regardless of its current
     * url_path or published state, or null when none exists. Used by the content-page
     * generators to decide whether to seed the initial body (a page that already exists
     * keeps its editor-managed body even after a rename) — so the guard tracks identity,
     * not the — now mutable — path.
     */
    public function findByGenerationKey(string $generationKey): ?Page;

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

    /**
     * Published discount-brand pages whose Offer belongs to one of the given
     * connections, with `pageable` (the Offer) eager-loaded. Powers the category
     * hub's "live brands" grid — a brand renders only when it has a live page.
     *
     * @param  array<int, int>  $connectionIds
     * @return Collection<int, Page>
     */
    public function liveDiscountBrandPagesForConnections(array $connectionIds): Collection;

    /**
     * Every published discount-brand page (with its Offer + connection eager-loaded),
     * ordered by `url_path` — the /discount/ directory ItemList over all brands.
     *
     * @return Collection<int, Page>
     */
    public function allPublishedDiscountBrandPages(): Collection;

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
