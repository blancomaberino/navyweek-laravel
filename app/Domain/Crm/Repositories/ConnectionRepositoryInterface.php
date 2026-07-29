<?php

declare(strict_types=1);

namespace App\Domain\Crm\Repositories;

use App\Domain\Crm\Models\Connection;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Data access for the Connection aggregate (brand/CRM). Callers depend on this
 * interface; the Eloquent implementation is bound in DomainServiceProvider.
 */
interface ConnectionRepositoryInterface
{
    /** The canonical connection owning this exact slug, or null. */
    public function findBySlug(string $slug): ?Connection;

    /**
     * Resolve a keyword-variant alias slug to its canonical connection, or null
     * when no alias matches. Successor to the `aliases.json` lookup.
     */
    public function findByAliasSlug(string $aliasSlug): ?Connection;

    /**
     * Idempotent upsert keyed on `slug` (importer-facing): creates or updates the
     * connection and returns the fresh model. One transaction per record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertBySlug(string $slug, array $attributes): Connection;

    /**
     * Connections whose research is due for re-verification as of `$asOf`
     * (`next_review_due` on or before the date). The DB successor to the legacy
     * 45-day `staleness-report.mjs`; feeds the scheduled FlagStaleResearch job.
     *
     * @return Collection<int, Connection>
     */
    public function dueForReview(DateTimeInterface $asOf): Collection;

    /**
     * Reconcile drift: connections that own a live page but have no research brief
     * (the YMYL/R6 violation). Both id sets are supplied by the Page/Research repos.
     *
     * @param  array<int, int>  $publishedIds
     * @param  array<int, int>  $researchedIds
     * @return Collection<int, Connection>
     */
    public function publishedPagesMissingResearch(array $publishedIds, array $researchedIds): Collection;

    /**
     * Reconcile drift: connections with a live page whose status isn't `published`
     * (and aren't duplicates).
     *
     * @param  array<int, int>  $publishedIds
     * @return Collection<int, Connection>
     */
    public function liveNotMarkedPublished(array $publishedIds): Collection;

    /**
     * Reconcile drift: connections with `duplicate_of` set but not marked `duplicate`.
     *
     * @return Collection<int, Connection>
     */
    public function duplicatesNotMarkedDuplicate(): Collection;
}
