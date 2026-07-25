<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Enums\VerificationProvider;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'internal_label' => 'Everyday military discount',
            'offer_type' => OfferType::Everyday,
            'headline_discount' => '20% off',
            'discount_summary' => $this->faker->sentence(),
            'verification' => VerificationProvider::IdMe,
            'verification_url' => 'https://example.com/verify',
            'official_url' => 'https://example.com/military',
            'audience_label' => 'Military & Veteran',
            'eligibility' => ['Active duty', 'Veterans', 'Retirees'],
            'exclusions' => ['Excludes sale items'],
            'key_facts' => [['label' => 'Verification', 'value' => 'ID.me']],
            'is_primary' => true,
            'sort_order' => 0,
            'is_published' => true,
        ];
    }

    /** A non-primary, unpublished secondary offer. */
    public function secondary(): static
    {
        return $this->state(fn (): array => [
            'internal_label' => 'Membership perk',
            'offer_type' => OfferType::Membership,
            'is_primary' => false,
            'sort_order' => 1,
            'is_published' => false,
        ]);
    }

    /** An advisory page documenting that the brand has no first-party discount. */
    public function advisory(): static
    {
        return $this->state(fn (): array => [
            'offer_type' => OfferType::AdvisoryNoDiscount,
            'headline_discount' => null,
            'verification' => null,
        ]);
    }
}
