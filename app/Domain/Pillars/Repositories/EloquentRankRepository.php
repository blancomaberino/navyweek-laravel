<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Models\Rank;
use Illuminate\Support\Collection;

final class EloquentRankRepository implements RankRepositoryInterface
{
    public function findBySlug(string $slug): ?Rank
    {
        return Rank::query()->where('slug', $slug)->first();
    }

    public function forCategory(RankCategory $category): Collection
    {
        // Stable grouping order for the list page. The *authoritative* rank
        // sequence is the `next_slug`/`previous_slug` linked list (walked at
        // render time); `paygrade` sorts lexically (so e.g. O-10 lands next to
        // O-1), so this is a grouping order, not the strict rank order.
        return Rank::query()
            ->where('category', $category->value)
            ->orderBy('paygrade')
            ->orderBy('name')
            ->get();
    }
}
