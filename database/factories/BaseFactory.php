<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\BaseType;
use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Enums\RegionType;
use App\Domain\Pillars\Models\Base;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Base>
 */
class BaseFactory extends Factory
{
    protected $model = Base::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Naval Station '.fake()->unique()->city();
        $slug = str($name)->slug()->value();

        return [
            'slug' => $slug,
            'name' => $name,
            'aka' => null,
            'type' => BaseType::NavalStation,
            // Defaults to a CONUS state-based base; use ->overseas() for OCONUS.
            'region_type' => RegionType::State,
            'state' => 'virginia',
            'state_name' => 'Virginia',
            'state_abbr' => 'VA',
            'country' => null,
            'country_slug' => null,
            'country_iso2' => null,
            'region' => null,
            'host_nation' => null,
            'timezone' => null,
            'local_currency' => null,
            'local_language' => null,
            'sofa_status' => null,
            'command_sponsorship_required' => null,
            'passport_required' => null,
            'city' => fake()->city(),
            'county' => null,
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'established' => fake()->numberBetween(1900, 2000),
            'personnel_count' => null,
            'area_acres' => null,
            'major_units' => ['Some Fleet Command'],
            'key_facts' => [['label' => 'Established', 'value' => '1917']],
            'meta_title' => $name.' — Guide',
            'meta_description' => 'Everything you need to know about '.$name.'.',
            'h1' => $name,
            'hero_tagline' => 'A U.S. Navy installation.',
            'seo_keyword_primary' => $slug,
            'commanding_officer' => null,
            'motto' => null,
            'nickname' => null,
            'wikipedia_url' => null,
            'official_url' => null,
            'notable_events' => null,
            'nearby_bases' => null,
            'nearest_fleet_week_slug' => null,
            'overview' => fake()->paragraph(),
            'history' => fake()->paragraph(),
            'location_context' => null,
            'host_nation_context' => null,
            'last_updated' => '2026-07-01',
        ];
    }

    /**
     * An overseas (OCONUS) installation — swaps the state fields for host-country
     * fields and flips the discriminator.
     */
    public function overseas(RegionType $regionType = RegionType::Country): self
    {
        return $this->state(fn (): array => [
            'region_type' => $regionType,
            'state' => null,
            'state_name' => null,
            'state_abbr' => null,
            'country' => 'Japan',
            'country_slug' => 'japan',
            'country_iso2' => 'JP',
            'region' => CombatantCommand::Pacom,
            'host_nation' => 'Japan',
            'timezone' => 'Asia/Tokyo',
            'local_currency' => 'JPY',
            'local_language' => ['ja', 'en'],
        ]);
    }
}
