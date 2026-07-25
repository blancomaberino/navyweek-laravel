<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\Offer;
use Illuminate\Support\Collection;

final class EloquentOfferRepository implements OfferRepositoryInterface
{
    public function forConnection(int $connectionId): Collection
    {
        // Eager-load the aggregate's children — this is the /discount/ page read
        // path, where accessing ->tiers / ->redemptionSteps per offer would N+1.
        return Offer::query()
            ->with(['tiers', 'redemptionSteps'])
            ->where('connection_id', $connectionId)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get();
    }

    public function primaryForConnection(int $connectionId): ?Offer
    {
        return Offer::query()
            ->where('connection_id', $connectionId)
            ->where('is_primary', true)
            ->first();
    }
}
