<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Support\DiscountCategoryOrdering;
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
     * The category's connections, brand A–Z — the baseline the curated ordering is
     * then applied to.
     *
     * This deliberately does NOT apply `pinned`/`order`/`excluded`. Those lists hold
     * **page** slugs (`marriott-military-discount`), not the connection slugs
     * (`marriott`) available here, so matching them in this method silently never
     * fired: the curated order was never applied and `excluded` never excluded.
     * {@see DiscountCategoryOrdering} applies them, in
     * the layer that has the page slug in hand.
     *
     * A–Z uses `strcasecmp` to mirror the legacy `localeCompare` (case-insensitive);
     * a raw spaceship would be byte-order, which sorts lowercase-styled brands
     * (adidas, eBay) after uppercase ones.
     */
    public function orderedConnections(DiscountCategory $category): Collection
    {
        return Connection::query()
            ->where('category', $category->match_category)
            ->get()
            ->sort(static fn (Connection $a, Connection $b): int => strcasecmp($a->brand, $b->brand))
            ->values();
    }
}
