<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\AffiliateLink;
use Illuminate\Support\Collection;

/**
 * Data access for outbound affiliate links. Callers depend on this interface;
 * the Eloquent implementation is bound in DomainServiceProvider.
 */
interface AffiliateLinkRepositoryInterface
{
    /**
     * All affiliate links for an offer, with their network eager-loaded (the
     * render path tags each link via the network's sub-ID param).
     *
     * @return Collection<int, AffiliateLink>
     */
    public function forOffer(int $offerId): Collection;
}
