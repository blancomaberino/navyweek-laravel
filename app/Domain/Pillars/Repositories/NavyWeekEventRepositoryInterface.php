<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Pillars\Models\NavyWeekEvent;
use Illuminate\Support\Collection;

/**
 * Data access for the Navy Week stops. Mirrors the legacy `data.ts` `events`
 * array reads (all in canonical sequence, filtered by status). Callers depend on
 * this interface; the Eloquent implementation is bound in DomainServiceProvider.
 */
interface NavyWeekEventRepositoryInterface
{
    public function findBySlug(string $slug): ?NavyWeekEvent;

    /**
     * Every stop in canonical order (the legacy numeric-id `sequence`).
     *
     * @return Collection<int, NavyWeekEvent>
     */
    public function all(): Collection;

    /**
     * Stops in a given lifecycle status, in canonical order.
     *
     * @return Collection<int, NavyWeekEvent>
     */
    public function byStatus(NavyWeekStatus $status): Collection;
}
