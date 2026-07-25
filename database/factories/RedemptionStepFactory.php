<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\RedemptionChannel;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Models\RedemptionStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RedemptionStep>
 */
class RedemptionStepFactory extends Factory
{
    protected $model = RedemptionStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'channel' => RedemptionChannel::Online,
            'title' => 'Verify your service',
            'detail' => $this->faker->sentence(),
            'sort_order' => 0,
        ];
    }

    /** An in-store redemption step. */
    public function inStore(): static
    {
        return $this->state(fn (): array => [
            'channel' => RedemptionChannel::InStore,
            'title' => 'Show your military ID',
        ]);
    }
}
