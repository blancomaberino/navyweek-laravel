<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\VeteransDayMeal;
use Illuminate\Support\Collection;

final class EloquentVeteransDayMealRepository implements VeteransDayMealRepositoryInterface
{
    public function findBySlug(string $slug): ?VeteransDayMeal
    {
        return VeteransDayMeal::query()->where('slug', $slug)->first();
    }

    public function verified(): Collection
    {
        // The YMYL render gate is defined once, on the model. Applying the
        // predicate (rather than re-expressing it as SQL `where` clauses that
        // could silently drift) keeps `isRenderable()` the single source of
        // truth — the row count is a seasonal handful, so the full scan is free.
        return VeteransDayMeal::query()
            ->orderBy('brand')
            ->get()
            ->filter(fn (VeteransDayMeal $meal): bool => $meal->isRenderable())
            ->values();
    }
}
