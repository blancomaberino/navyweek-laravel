<?php

declare(strict_types=1);

namespace App\Domain\Crm\Repositories;

use App\Domain\Crm\Enums\ConnectionStatus;
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

    /**
     * Lock the connection's row, stamp `last_verified_at = $verifiedAt`, recompute
     * `next_review_due = $verifiedAt + research_cadence_days` (read from the locked
     * row so a concurrent cadence edit can't be lost), and persist. Must be called
     * inside a transaction so the lock is held; returns the locked/updated model.
     */
    public function recordVerification(Connection $connection, DateTimeInterface $verifiedAt): Connection;

    /**
     * Lock the connection's row `FOR UPDATE` and return it (null if absent/trashed),
     * so a caller can serialize a read-then-write sequence on the connection. Must be
     * called inside a transaction.
     */
    public function lockById(int $connectionId): ?Connection;

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

    /** Total connections in the universe. */
    public function total(): int;

    /** Connections in the given pipeline status. */
    public function countByStatus(ConnectionStatus $status): int;

    /** Connections whose research is due for re-verification as of `$asOf`. */
    public function dueForReviewCount(DateTimeInterface $asOf): int;

    /** Connections still in the backlog (`is_backlog`). */
    public function backlogCount(): int;

    /**
     * Bulk-set the pipeline status on the given connection ids (CRM bulk action).
     *
     * @param  array<int, int|string>  $ids
     * @return int rows affected
     */
    public function updateStatusForIds(array $ids, ConnectionStatus $status): int;

    /**
     * Bulk-clear `is_backlog` on the given connection ids (promote from backlog).
     *
     * @param  array<int, int|string>  $ids
     * @return int rows affected
     */
    public function clearBacklogForIds(array $ids): int;
}
