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

    public function forCategoryByPaygrade(RankCategory $category): Collection
    {
        // Numeric paygrade sort done in PHP — portable across SQLite/MySQL, unlike a
        // CAST(SUBSTRING(...)) orderByRaw. Paygrades are unique within a category, so
        // no tiebreak is needed (and PHP's sortBy is stable regardless).
        return Rank::query()
            ->where('category', $category->value)
            ->get()
            ->sortBy(fn (Rank $rank): int => self::paygradeNumber($rank->paygrade))
            ->values();
    }

    public function activeRatings(): Collection
    {
        return Rank::query()
            ->where('category', RankCategory::RatingActive->value)
            ->orderBy('name')
            ->get();
    }

    public function designators(): Collection
    {
        return Rank::query()
            ->where('category', RankCategory::OfficerDesignator->value)
            ->orderBy('designator_code')
            ->get();
    }

    public function historicRatings(): Collection
    {
        return Rank::query()
            ->where('category', RankCategory::RatingHistorical->value)
            ->orderByDesc('decommissioned_year')
            ->orderBy('id')
            ->get();
    }

    /** The integer after the paygrade's tier letter ("O-10" → 10, "E-1" → 1). */
    private static function paygradeNumber(string $paygrade): int
    {
        $dash = strpos($paygrade, '-');

        return $dash === false ? 0 : (int) substr($paygrade, $dash + 1);
    }
}
