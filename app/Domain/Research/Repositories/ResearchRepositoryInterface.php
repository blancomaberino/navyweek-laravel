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
     * The full version history for a connection, newest first.
     *
     * @return Collection<int, Research>
     */
    public function historyForConnection(int $connectionId): Collection;

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
