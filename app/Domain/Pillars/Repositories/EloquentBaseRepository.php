<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\BaseType;
use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Models\Base;
use Illuminate\Support\Collection;

final class EloquentBaseRepository implements BaseRepositoryInterface
{
    public function findBySlug(string $slug): ?Base
    {
        return Base::query()->where('slug', $slug)->first();
    }

    public function all(): Collection
    {
        return Base::query()->orderBy('name')->get();
    }

    public function forState(string $stateSlug): Collection
    {
        return Base::query()
            ->where('state', $stateSlug)
            ->orderBy('name')
            ->get();
    }

    public function forCountry(string $countrySlug): Collection
    {
        return Base::query()
            ->where('country_slug', $countrySlug)
            ->orderBy('name')
            ->get();
    }

    public function forType(BaseType $type): Collection
    {
        return Base::query()
            ->where('type', $type->value)
            ->orderBy('name')
            ->get();
    }

    public function forRegion(CombatantCommand $region): Collection
    {
        return Base::query()
            ->where('region', $region->value)
            ->orderBy('name')
            ->get();
    }
}
