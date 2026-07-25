<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\AffiliateNetwork;

/**
 * Data access for the affiliate-network registry. Callers depend on this
 * interface; the Eloquent implementation is bound in DomainServiceProvider.
 */
interface AffiliateNetworkRepositoryInterface
{
    /** The network with this registry key (e.g. "impact", "direct"), or null. */
    public function findByKey(string $key): ?AffiliateNetwork;
}
