<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\VeteransDayMeal;
use Illuminate\Support\Collection;

/**
 * Data access for the Veterans Day meal roundup. Callers depend on this
 * interface; the Eloquent implementation is bound in DomainServiceProvider.
 */
interface VeteransDayMealRepositoryInterface
{
    public function findBySlug(string $slug): ?VeteransDayMeal;

    /**
     * The renderable roundup — only `Verified` offers that carry a primary
     * `source_url` (the legacy YMYL render gate), ordered by brand.
     *
     * @return Collection<int, VeteransDayMeal>
     */
    public function verified(): Collection;
}
