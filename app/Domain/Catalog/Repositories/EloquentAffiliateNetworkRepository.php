<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\AffiliateNetwork;

final class EloquentAffiliateNetworkRepository implements AffiliateNetworkRepositoryInterface
{
    public function findByKey(string $key): ?AffiliateNetwork
    {
        return AffiliateNetwork::query()->where('key', $key)->first();
    }
}
