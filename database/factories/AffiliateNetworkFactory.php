<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\AffiliateNetwork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateNetwork>
 */
class AffiliateNetworkFactory extends Factory
{
    protected $model = AffiliateNetwork::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = $this->faker->unique()->slug(1);

        return [
            'key' => $key,
            'name' => str($key)->headline()->value(),
            'subid_param' => 'subId1',
            'extra_params' => null,
        ];
    }

    /** The `direct` network (UTM fallback). */
    public function direct(): static
    {
        return $this->state(fn (): array => [
            'key' => 'direct',
            'name' => 'Direct (UTM fallback)',
            'subid_param' => 'utm_content',
            'extra_params' => ['utm_source' => 'navyweek', 'utm_medium' => 'referral'],
        ]);
    }
}
