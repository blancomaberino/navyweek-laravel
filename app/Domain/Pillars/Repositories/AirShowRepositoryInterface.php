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
     * Every show in the registry, published or not — the legacy `airShows` export
     * that fills the hub directory table (publication gates only the guide LINK
     * in the last column, never the row). Same ordering as `published()`.
     *
     * @return Collection<int, AirShow>
     */
    public function directory(): Collection;

    /**
     * Published guides only (the render gate for the hub listing + ItemList),
     * ordered by start date with date-unconfirmed shows last — the legacy list
     * order that fixes the hub ItemList positions.
     *
     * @return Collection<int, AirShow>
     */
    public function published(): Collection;

    /** The single air-show hub content record (the `/air-show/` landing page). */
    public function hub(): ?AirShowHubMeta;
}
