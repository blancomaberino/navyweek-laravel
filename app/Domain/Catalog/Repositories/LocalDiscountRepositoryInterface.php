<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\LocalDiscount;
use Illuminate\Support\Collection;

/**
 * Data access for the local-business discount pages. Mirrors the legacy
 * `localDiscounts/index.ts` reads (a single page by its state/city/business
 * triple, and the state / city rollup hubs). Callers depend on this interface;
 * the Eloquent implementation is bound in DomainServiceProvider.
 */
interface LocalDiscountRepositoryInterface
{
    /**
     * The single page identified by its URL triple:
     * `/discounts/<state>/<city>/<business_slug>/`.
     */
    public function find(string $state, string $city, string $businessSlug): ?LocalDiscount;

    /**
     * Every local page, with `stores.hours` eager-loaded — the page-generation sweep
     * (and the source for the state/city rollups). Ordered by state, city, company for
     * a deterministic build.
     *
     * @return Collection<int, LocalDiscount>
     */
    public function all(): Collection;

    /**
     * The distinct states that have local pages, for the `/discounts/` root hub —
     * each `{state, state_name, count}`, ordered by state name.
     *
     * @return Collection<int, array{state: string, state_name: string, count: int}>
     */
    public function states(): Collection;

    /**
     * Every local page in a state (the `/discounts/<state>/` rollup), by state slug.
     *
     * @return Collection<int, LocalDiscount>
     */
    public function forState(string $stateSlug): Collection;

    /**
     * Every local page in a city (the `/discounts/<state>/<city>/` rollup).
     *
     * @return Collection<int, LocalDiscount>
     */
    public function forCity(string $stateSlug, string $citySlug): Collection;
}
