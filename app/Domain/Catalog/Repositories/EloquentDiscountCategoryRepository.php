<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Crm\Models\Connection;
use Illuminate\Support\Collection;

final class EloquentDiscountCategoryRepository implements DiscountCategoryRepositoryInterface
{
    public function findBySlug(string $slug): ?DiscountCategory
    {
        return DiscountCategory::query()->where('slug', $slug)->first();
    }

    public function all(): Collection
    {
        return DiscountCategory::query()->orderBy('id')->get();
    }

    /**
     * Port of the legacy `orderCategoryDiscounts`. The connection set is matched
     * on `category = match_category` here (rather than being passed in as the
     * legacy registry); which of those connections is "live" is a render-time
     * concern applied by the caller in Phase 3, not the ordering algorithm.
     */
    public function orderedConnections(DiscountCategory $category): Collection
    {
        $excluded = collect($category->excluded ?? [])->flip();

        // Both legacy modes are the same shape — named brands first in their given
        // order, everyone else to the end A–Z by brand — differing only in which
        // slug list supplies the priority. An explicit full `order` wins over
        // `pinned` (matching the legacy `if (order) … else (pinned) …`).
        $priority = collect($category->order ?: $category->pinned ?? [])->flip();

        return Connection::query()
            ->where('category', $category->match_category)
            ->get()
            ->reject(fn (Connection $c): bool => $excluded->has($c->slug))
            // Sort by priority position (unlisted brands to the end), then brand
            // A–Z as the tiebreak. `strcasecmp` mirrors the legacy `localeCompare`
            // (case-insensitive) — a raw string spaceship would be byte-order, which
            // sorts lowercase-styled brands (adidas, eBay) after uppercase ones.
            ->sort(function (Connection $a, Connection $b) use ($priority): int {
                $ai = $priority->has($a->slug) ? (int) $priority->get($a->slug) : PHP_INT_MAX;
                $bi = $priority->has($b->slug) ? (int) $priority->get($b->slug) : PHP_INT_MAX;

                return $ai <=> $bi ?: strcasecmp($a->brand, $b->brand);
            })
            ->values();
    }
}
