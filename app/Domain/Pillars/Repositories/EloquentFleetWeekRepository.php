<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\FleetWeekSeason;
use App\Domain\Pillars\Models\FleetWeek;
use Illuminate\Support\Collection;

final class EloquentFleetWeekRepository implements FleetWeekRepositoryInterface
{
    public function findBySlug(string $slug): ?FleetWeek
    {
        return FleetWeek::query()->where('slug', $slug)->first();
    }

    public function all(): Collection
    {
        return FleetWeek::query()->orderBy('city')->get();
    }

    public function forSeason(FleetWeekSeason $season): Collection
    {
        return FleetWeek::query()
            ->where('season', $season->value)
            ->orderBy('city')
            ->get();
    }
}
