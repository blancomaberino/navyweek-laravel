<?php

declare(strict_types=1);

namespace App\Domain\Research\Repositories;

use App\Domain\Research\Models\Research;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Data access for the Research aggregate. Callers depend on this interface; the
 * Eloquent implementation is bound in DomainServiceProvider.
 */
interface ResearchRepositoryInterface
{
    /**
     * The most recent brief for a connection (highest `version`), or null when the
     * connection has none. This is the current source of truth for its page.
     */
    public function latestForConnection(int $connectionId): ?Research;

    /**
     * The latest brief (highest `version`) for each of the given connection ids, in
     * one query, keyed by `connection_id`. The batched form of `latestForConnection`
     * for sweeps that would otherwise fire a query per connection.
     *
     * @param  array<int, int>  $connectionIds
     * @return Collection<int, Research>
     */
    public function latestForConnections(array $connectionIds): Collection;

    /**
     * The full version history for a connection, newest first.
     *
     * @return Collection<int, Research>
     */
    public function historyForConnection(int $connectionId): Collection;

    /**
     * Lock the brief and, only if it is currently `Complete`, transition it to
     * `Stale` (a past-cadence brief). A `Draft` is mid-flight and a
     * `Superseded`/`Stale` brief is already terminal, so both are left untouched.
     * Idempotent; must run inside the caller's transaction.
     */
    public function markStale(Research $research): void;

    /**
     * Lock the brief's row, stamp it Complete with `last_verified = $verifiedAt`, and
     * persist. Must be called inside a transaction so the lock is held; returns the
     * locked/updated model.
     */
    public function markVerified(Research $research, DateTimeInterface $verifiedAt): Research;

    /**
     * Distinct connection ids that have at least one research brief (reconcile gate).
     *
     * @return array<int, int>
     */
    public function connectionIdsWithBriefs(): array;
}
