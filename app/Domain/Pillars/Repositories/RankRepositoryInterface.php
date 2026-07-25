<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Models\Rank;
use Illuminate\Support\Collection;

/**
 * Data access for the Rank pillar. The list pages (/navy-ranks/, /navy-ratings/)
 * group entries by category; the designator pages read one entry. Callers depend
 * on this interface; the Eloquent implementation is bound in DomainServiceProvider.
 */
interface RankRepositoryInterface
{
    public function findBySlug(string $slug): ?Rank;

    /**
     * All entries in a category, ordered by paygrade then name (the list-page read).
     *
     * @return Collection<int, Rank>
     */
    public function forCategory(RankCategory $category): Collection;
}
