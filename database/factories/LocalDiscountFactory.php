<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\LocalVerification;
use App\Domain\Catalog\Models\LocalDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalDiscount>
 */
class LocalDiscountFactory extends Factory
{
    protected $model = LocalDiscount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = ucfirst(fake()->unique()->words(2, true));
        $businessSlug = str($company)->slug()->value();

        return [
            'state' => 'texas',
            'state_name' => 'Texas',
            'state_abbr' => 'TX',
            'city' => 'houston',
            'city_name' => 'Houston',
            'business_slug' => $businessSlug,
            'company' => $company,
            'business_type' => 'Attraction',
            'category' => 'Attractions',
            'logo' => null,
            'logo_alt' => null,
            'logo_background' => null,
            'official_url' => 'https://example.com/'.$businessSlug.'/military',
            'brand_home_url' => 'https://example.com/'.$businessSlug,
            'headline_discount' => 'Free admission for active-duty military',
            'discount_summary' => 'Active-duty members get free general admission with ID.',
            'verification' => LocalVerification::InStoreId,
            'verification_detail' => 'Show a valid military ID at the box office.',
            'active_duty' => true,
            'veterans' => false,
            'retirees' => false,
            'reserve_guard' => false,
            'military_family' => true,
            'eligibility' => ['Active-duty service members', 'Their dependents'],
            'tiers' => [['audience' => 'Active duty', 'amount' => 'Free', 'note' => 'With ID']],
            'redeem_in_store' => [['title' => 'Visit the box office', 'detail' => 'Present your military ID.']],
            'exclusions' => ['Special exhibitions may cost extra.'],
            'nearby_bases' => [['name' => 'Ellington Field', 'branch' => 'Joint Reserve', 'distanceMi' => 18]],
            'service_area' => 'Greater Houston metro',
            'price_range' => '$$',
            'intro' => ['A local intro paragraph.'],
            'details' => ['A details paragraph.'],
            'key_facts' => [['label' => 'Discount', 'value' => 'Free admission']],
            'meta_title' => $company.' Military Discount — Houston, TX',
            'meta_description' => 'How active-duty military save at '.$company.' in Houston.',
            'h1' => $company.' Military Discount',
            'hero_tagline' => 'Free admission for active-duty military in Houston.',
            'primary_keyword' => $businessSlug.' military discount',
            'og_image' => null,
            'date_published' => '2026-07-23',
            'date_modified' => '2026-07-23',
            'last_verified' => 'July 23, 2026',
        ];
    }
}
