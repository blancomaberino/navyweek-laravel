<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\FleetWeekSeason;
use App\Domain\Pillars\Enums\FleetWeekStatus;
use App\Domain\Pillars\Models\FleetWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetWeek>
 */
class FleetWeekFactory extends Factory
{
    protected $model = FleetWeek::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->unique()->city();
        $slug = str($city)->slug()->value();

        return [
            'slug' => $slug,
            'city' => $city,
            'state' => 'California',
            'state_abbr' => 'CA',
            'year' => 2026,
            'branding_name' => $city.' Fleet Week',
            'season' => FleetWeekSeason::Fall,
            'month_label' => 'October',
            // Defaults to a full official fleet week; use ->tierThree() for a
            // city with no standing event.
            'has_official_fleet_week' => true,
            'has_air_show' => true,
            'status' => FleetWeekStatus::Scheduled,
            'status_label' => 'Scheduled',
            'status_note' => null,
            'festival_dates_label' => 'October 4–12, 2026',
            'airshow_dates_label' => 'October 9–11, 2026',
            'dek' => 'The Blue Angels return to the bay.',
            'intro' => ['A lead paragraph.'],
            'quick_facts' => [['label' => 'Dates', 'value' => 'Oct 4–12']],
            'official_url' => 'https://example.com/fleetweek',
            'official_site_label' => 'example.com',
            'schedule' => [[
                'date' => 'Oct 9',
                'day' => 'Fri',
                'event' => 'Air show + Parade of Ships',
                'time' => 'Gates 10 a.m.',
                'location' => 'Marina Green',
            ]],
            'schedule_note' => 'Times subject to change.',
            'airshow' => [
                'paragraphs' => ['The air show anchors the weekend.'],
                'performers' => [['name' => 'Blue Angels']],
                'showWindow' => 'Gates 10–11 a.m.',
            ],
            'parade_of_ships' => ['paragraphs' => ['Ships transit the bay.']],
            'ship_tours' => ['paragraphs' => ['Free tours at the piers.'], 'rules' => ['No large bags.']],
            'viewing_intro' => 'Best spots along the waterfront.',
            'viewing_spots' => [['name' => 'Marina Green', 'why' => 'Front-row seats.', 'transit' => 'Muni 30']],
            'getting_there' => ['Take transit; parking is limited.'],
            'history' => ['Fleet Week began decades ago.'],
            'past_years' => [['year' => '2025', 'note' => 'Record crowds.']],
            'festival' => [
                'name' => $city.' Fleet Week 2026',
                'startDate' => '2026-10-04',
                'endDate' => '2026-10-12',
                'eventStatus' => 'EventScheduled',
                'performers' => [['name' => 'Blue Angels']],
                'organizer' => ['name' => 'Fleet Week Association', 'url' => 'https://example.com'],
                'location' => ['name' => 'Marina Green', 'locality' => $city, 'region' => 'CA'],
                'description' => 'The annual fleet week celebration.',
            ],
            'card_summary' => 'The bay area fleet week.',
            'related_slugs' => [],
            'meta_title' => $city.' Fleet Week 2026',
            'meta_description' => 'Everything about '.$city.' Fleet Week.',
            'h1' => $city.' Fleet Week',
            'primary_keyword' => $slug.' fleet week',
            'og_image' => '/og/fleetweek/'.$slug.'.png',
            'date_published' => '2026-06-10',
            'date_modified' => '2026-06-10',
            'last_verified' => 'June 10, 2026',
        ];
    }

    /** A Tier-3 city with no standing official fleet week. */
    public function tierThree(): self
    {
        return $this->state(fn (): array => [
            'has_official_fleet_week' => false,
            'has_air_show' => false,
            'status' => FleetWeekStatus::None,
            'status_label' => 'No official fleet week',
            'festival_dates_label' => null,
            'airshow_dates_label' => null,
            'airshow' => null,
            'parade_of_ships' => null,
            'ship_tours' => null,
            'festival' => null,
        ]);
    }
}
