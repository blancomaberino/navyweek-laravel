<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Models\AffiliateLink;
use Illuminate\Support\Collection;

final class EloquentAffiliateLinkRepository implements AffiliateLinkRepositoryInterface
{
    public function forOffer(int $offerId): Collection
    {
        return AffiliateLink::query()
            ->with('network')
            ->where('offer_id', $offerId)
            ->get();
    }
}
