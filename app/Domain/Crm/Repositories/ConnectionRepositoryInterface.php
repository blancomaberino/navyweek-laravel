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
     * @return int  rows affected
     */
    public function updateStatusForIds(array $ids, ConnectionStatus $status): int;

    /**
     * Bulk-clear `is_backlog` on the given connection ids (promote from backlog).
     *
     * @param  array<int, int|string>  $ids
     * @return int  rows affected
     */
    public function clearBacklogForIds(array $ids): int;
}
