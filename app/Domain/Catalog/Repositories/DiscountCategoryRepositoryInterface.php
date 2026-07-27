<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Crm\Models\Connection;
use Illuminate\Support\Collection;

/**
 * Data access for the discount category hubs. Mirrors the legacy `categories.ts`
 * helpers (`getCategory`, `orderCategoryDiscounts`). Callers depend on this
 * interface; the Eloquent implementation is bound in DomainServiceProvider.
 */
interface DiscountCategoryRepositoryInterface
{
    public function findBySlug(string $slug): ?DiscountCategory;

    /**
     * All category hubs in id order (mirrors the legacy registry insertion order).
     *
     * @return Collection<int, DiscountCategory>
     */
    public function all(): Collection;

    /**
     * The ordered brand list for a hub — the port of `orderCategoryDiscounts`.
     * Matches connections on `category = match_category`, drops `excluded` slugs,
     * then orders by explicit `order` (unnamed brands fall to the end, A–Z by
     * brand) or, absent that, `pinned` first then the rest A–Z by brand.
     *
     * @return Collection<int, Connection>
     */
    public function orderedConnections(DiscountCategory $category): Collection;
}
