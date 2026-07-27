<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use Illuminate\Support\Collection;

/**
 * Data access for the air-show silo (event guides + the directory hub). Mirrors
 * the legacy `airshows` reads: a guide by slug, the published-only hub listing,
 * and the single hub-meta record. Callers depend on this interface; the Eloquent
 * implementation is bound in DomainServiceProvider.
 */
interface AirShowRepositoryInterface
{
    public function findBySlug(string $slug): ?AirShow;

    /**
     * Published guides only (the render gate for the hub listing), ordered by
     * short name.
     *
     * @return Collection<int, AirShow>
     */
    public function published(): Collection;

    /** The single air-show hub content record (the `/air-show/` landing page). */
    public function hub(): ?AirShowHubMeta;
}
