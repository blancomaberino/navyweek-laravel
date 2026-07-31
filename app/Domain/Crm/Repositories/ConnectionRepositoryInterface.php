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
     * Lock the connection and move it to `needs-reverify` — but ONLY from an active
     * (`published`/`drafted`) state, re-checked under the lock so a connection that
     * left the active set since it was read (e.g. edited to `skipped`/`duplicate`)
     * isn't clobbered. Returns true if it transitioned, false if skipped/absent. Must
     * run inside the caller's transaction. Mirrors `ResearchRepository::markStale`.
     */
    public function markNeedsReverify(Connection $connection): bool;
}
