<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\BaseType;
use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Models\Base;
use Illuminate\Support\Collection;

/**
 * Data access for the Base pillar. Mirrors the legacy `bases/index.ts` hub reads
 * (by state / country / region / type). Callers depend on this interface; the
 * Eloquent implementation is bound in DomainServiceProvider.
 */
interface BaseRepositoryInterface
{
    public function findBySlug(string $slug): ?Base;

    /**
     * Every base, ordered by name — the read behind pillar-page generation (one
     * `pages` row per base) and any full-directory build.
     *
     * @return Collection<int, Base>
     */
    public function all(): Collection;

    /**
     * State-based bases in a state, by state slug (the `/bases/<state>/` hub).
     *
     * @return Collection<int, Base>
     */
    public function forState(string $stateSlug): Collection;

    /**
     * Overseas bases in a host country, by country slug (`/bases/<country>/`).
     *
     * @return Collection<int, Base>
     */
    public function forCountry(string $countrySlug): Collection;

    /**
     * Bases of an installation type (the type hub).
     *
     * @return Collection<int, Base>
     */
    public function forType(BaseType $type): Collection;

    /**
     * Overseas bases under a combatant command (the region hub).
     *
     * @return Collection<int, Base>
     */
    public function forRegion(CombatantCommand $region): Collection;
}
