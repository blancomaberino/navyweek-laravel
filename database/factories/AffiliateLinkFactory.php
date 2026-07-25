<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\Placement;
use App\Domain\Catalog\Models\AffiliateLink;
use App\Domain\Catalog\Models\AffiliateNetwork;
use App\Domain\Catalog\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateLink>
 */
class AffiliateLinkFactory extends Factory
{
    protected $model = AffiliateLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => null,
            'offer_id' => Offer::factory(),
            'affiliate_network_id' => AffiliateNetwork::factory(),
            'base_url' => 'https://example.com/military',
            'placement' => Placement::HeroCta,
            'rel' => 'sponsored noopener noreferrer',
        ];
    }
}
