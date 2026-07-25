<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\Offer;
use Illuminate\Support\Collection;

/**
 * Data access for the Offer aggregate. Callers depend on this interface; the
 * Eloquent implementation is bound in DomainServiceProvider.
 */
interface OfferRepositoryInterface
{
    /**
     * All offers for a connection, primary first then by sort order.
     *
     * @return Collection<int, Offer>
     */
    public function forConnection(int $connectionId): Collection;

    /**
     * The primary offer for a connection (drives the brand's main /discount/
     * page), or null when the connection has none.
     */
    public function primaryForConnection(int $connectionId): ?Offer;
}
