<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Models\OfferTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferTier>
 */
class OfferTierFactory extends Factory
{
    protected $model = OfferTier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'audience' => 'Military (active, reserve, Guard, veterans, retirees)',
            'amount' => '20% off',
            'note' => null,
            'sort_order' => 0,
        ];
    }
}
