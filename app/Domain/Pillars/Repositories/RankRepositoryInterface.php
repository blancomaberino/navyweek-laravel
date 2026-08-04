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

    /**
     * All entries in a category ordered by the true numeric paygrade (E-1→E-9,
     * O-1→O-10), not the lexical `paygrade` string (which puts O-10 next to O-1).
     * Powers the `/navy-ranks/` list-page sections. Ascending; the view reverses
     * for the high→low display.
     *
     * @return Collection<int, Rank>
     */
    public function forCategoryByPaygrade(RankCategory $category): Collection;

    /**
     * Active enlisted ratings ordered alphabetically by name — the `/navy-ratings/`
     * list order (grouped by rating community in the view).
     *
     * @return Collection<int, Rank>
     */
    public function activeRatings(): Collection;

    /**
     * Historic enlisted ratings, most-recently-decommissioned first.
     *
     * @return Collection<int, Rank>
     */
    public function historicRatings(): Collection;

    /**
     * Every officer designator (category `officer-designator`), ordered by its
     * four-digit code — the source for the designators hub, its community hubs,
     * and each detail page.
     *
     * @return Collection<int, Rank>
     */
    public function designators(): Collection;
}
