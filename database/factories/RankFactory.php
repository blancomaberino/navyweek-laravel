<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\DesignatorCommunity;
use App\Domain\Pillars\Enums\HistoricRatingEra;
use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Enums\RatingCommunity;
use App\Domain\Pillars\Models\Rank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rank>
 */
class RankFactory extends Factory
{
    protected $model = Rank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Defaults to an officer-commissioned rank; use the category states below.
        $name = 'Officer '.fake()->unique()->numerify('####');
        $slug = str($name)->slug()->value();

        return [
            'slug' => $slug,
            'category' => RankCategory::OfficerCommissioned,
            'name' => $name,
            'abbreviation' => 'ENS',
            'paygrade' => 'O-1',
            'insignia_path' => '/insignia/'.$slug.'.svg',
            'insignia_alt' => $name.' insignia',
            'meta_title' => $name.' — Navy Rank Guide',
            'meta_description' => 'Everything about the '.$name.' rank.',
            'h1' => $name,
            'hero_tagline' => 'A U.S. Navy rank.',
            'overview' => fake()->paragraph(),
            'history' => fake()->paragraph(),
            'responsibilities' => ['Lead a division'],
            'addressing' => $name,
            'prerequisites' => ['Commissioning source'],
            'common_assignments' => ['Division Officer'],
            'pay_range' => ['min_usd_monthly' => 3637, 'max_usd_monthly' => 4576, 'pay_data_year' => 2025],
            'last_updated' => '2026-07-01',
            'nato_code' => 'OF-1',
            'is_flag_officer' => false,
        ];
    }

    public function warrant(): self
    {
        return $this->state(fn (): array => [
            'category' => RankCategory::OfficerWarrant,
            'abbreviation' => 'CWO2',
            'paygrade' => 'W-2',
            'nato_code' => 'WO-2',
            'is_flag_officer' => null,
        ]);
    }

    public function enlisted(): self
    {
        return $this->state(fn (): array => [
            'category' => RankCategory::EnlistedPaygrade,
            'abbreviation' => 'CPO',
            'paygrade' => 'E-7',
            'nato_code' => 'OR-7',
            'is_flag_officer' => null,
            'is_chief' => true,
        ]);
    }

    public function designator(): self
    {
        return $this->state(fn (): array => [
            'category' => RankCategory::OfficerDesignator,
            'paygrade' => 'O-1 to O-6',
            'nato_code' => null,
            'is_flag_officer' => null,
            'designator_code' => '1110',
            'designator_community' => DesignatorCommunity::UnrestrictedLine,
            'commissioning_sources' => ['USNA', 'NROTC'],
            'career_path' => [['paygrade' => 'O-1', 'title' => 'Division Officer', 'description' => 'First tour']],
            'training_pipeline' => [['name' => 'BDOC', 'location' => 'Newport', 'duration' => '12 weeks', 'description' => 'Basic course']],
            'related_designators' => [],
        ]);
    }

    public function ratingActive(): self
    {
        return $this->state(fn (): array => [
            'category' => RankCategory::RatingActive,
            'abbreviation' => 'BM',
            'paygrade' => 'E-1 to E-9',
            'nato_code' => null,
            'is_flag_officer' => null,
            'rating_community' => RatingCommunity::General,
            'what_they_do' => 'Operate and maintain deck equipment.',
            'asvab_requirement' => 'VE+AR',
            'a_school_location' => 'Great Lakes, IL',
            'a_school_duration' => '7 weeks',
            'typical_platforms' => ['Destroyers', 'Cruisers'],
            'career_path' => [['paygrade' => 'E-4', 'title' => 'Petty Officer', 'description' => 'Journeyman']],
            'training_pipeline' => [['name' => 'A School', 'location' => 'Great Lakes', 'duration' => '7 weeks', 'description' => 'Rating school']],
            'related_ratings' => [],
        ]);
    }

    public function ratingHistorical(): self
    {
        return $this->ratingActive()->state(fn (): array => [
            'category' => RankCategory::RatingHistorical,
            'active_period' => '1948–2016',
            'years_active' => '68 years',
            'decommissioned_year' => 2016,
            'decommission_reason' => 'Merged into a successor rating.',
            'successor_ratings' => [],
            'notable_for' => ['First of its kind'],
            'era_tags' => [HistoricRatingEra::ColdWar, HistoricRatingEra::Consolidation2010s],
        ]);
    }
}
