<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\MealEligibility;
use App\Domain\Catalog\Enums\MealRedemption;
use App\Domain\Catalog\Enums\MealStatus;
use App\Domain\Catalog\Models\VeteransDayMeal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VeteransDayMeal>
 */
class VeteransDayMealFactory extends Factory
{
    protected $model = VeteransDayMeal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brand = ucfirst(fake()->unique()->company());
        $slug = str($brand)->slug()->value();

        return [
            'slug' => $slug,
            'brand' => $brand,
            'discount_slug' => null,
            'offer' => 'Free full-size entrée from a Veterans Day menu',
            'eligibility' => [MealEligibility::Veteran, MealEligibility::Active],
            'dependents_eligible' => false,
            'redemption' => MealRedemption::DineIn,
            'proof_required' => 'Military ID, VA card, DD214, or uniform',
            'offer_date' => '2026-11-11',
            'nationwide' => false,
            'source_url' => 'https://example.com/'.$slug.'/veterans-day',
            'source_label' => $brand.' official site',
            'last_verified_at' => '2026-06-29',
            // Defaults to the renderable state; use ->pending()/->discontinued().
            'status' => MealStatus::Verified,
            'notes' => 'Participating locations only; dine-in only.',
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => ['status' => MealStatus::Pending]);
    }

    public function discontinued(): self
    {
        return $this->state(fn (): array => ['status' => MealStatus::Discontinued]);
    }
}
