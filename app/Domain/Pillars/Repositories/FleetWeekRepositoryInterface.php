<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\FleetWeekSeason;
use App\Domain\Pillars\Models\FleetWeek;
use Illuminate\Support\Collection;

/**
 * Data access for the Fleet Week city guides. Mirrors the legacy `fleetweek`
 * hub reads (all cities, and the season-grouped hub). Callers depend on this
 * interface; the Eloquent implementation is bound in DomainServiceProvider.
 */
interface FleetWeekRepositoryInterface
{
    public function findBySlug(string $slug): ?FleetWeek;

    /**
     * Every fleet-week city, ordered by city.
     *
     * @return Collection<int, FleetWeek>
     */
    public function all(): Collection;

    /**
     * Fleet-week cities in a season bucket (the hub grouping), ordered by city.
     *
     * @return Collection<int, FleetWeek>
     */
    public function forSeason(FleetWeekSeason $season): Collection;
}
