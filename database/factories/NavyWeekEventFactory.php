<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Pillars\Models\NavyWeekEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NavyWeekEvent>
 */
class NavyWeekEventFactory extends Factory
{
    protected $model = NavyWeekEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->unique()->city();
        $slug = str($city)->slug()->value();

        return [
            'sequence' => fake()->unique()->numberBetween(1, 100000),
            'slug' => $slug,
            'city' => $city,
            'state' => 'Texas',
            'state_abbr' => 'TX',
            'start_date' => '2026-01-26',
            'end_date' => '2026-02-01',
            'anchor_event' => 'Texas Citrus Fiesta',
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'first_time' => true,
            'first_time_location' => null,
            'first_time_badge' => null,
            'status' => NavyWeekStatus::Upcoming,
            // City detail defaults to unset (the core stop always exists);
            // use ->withCityDetail() to populate the rich block.
            'anchor_event_detail' => null,
            'anchor_event_url' => null,
            'first_time_note' => null,
            'navy_assets' => null,
            'key_venues' => null,
            'military_context' => null,
            'navco_url' => null,
            'highlights' => null,
            'venues' => null,
            'daily_schedule' => null,
            'parking_notes' => null,
            'cost_summary' => null,
            'last_verified_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (): array => ['status' => NavyWeekStatus::Completed]);
    }

    public function withCityDetail(): self
    {
        return $this->state(fn (): array => [
            'anchor_event_detail' => 'The anchor event runs all week.',
            'anchor_event_url' => 'https://example.com/anchor',
            'first_time_note' => 'First Navy Week here.',
            'navy_assets' => ['USS Example'],
            'key_venues' => ['Downtown plaza'],
            'military_context' => ['Nearby installation history.'],
            'navco_url' => 'https://outreach.navy.mil/example',
            'highlights' => ['Parade', 'Ship tours'],
            'venues' => [[
                'name' => 'City Hall',
                'address' => '1 Main St',
                'lat' => 29.76,
                'lng' => -95.36,
                'notes' => 'Kickoff venue.',
                'parking' => 'Free lot.',
                'source_level' => 'navco',
            ]],
            'daily_schedule' => [[
                'date' => '2026-01-26',
                'tba' => false,
                'items' => [[
                    'time' => '10:00',
                    'title' => 'Opening ceremony',
                    'venue' => 'City Hall',
                    'description' => 'Proclamation.',
                    'source' => 'https://outreach.navy.mil/example',
                    'source_level' => 'navco',
                ]],
            ]],
            'parking_notes' => 'Use the downtown garages.',
            'cost_summary' => 'Most events are free.',
            'last_verified_at' => '2026-07-13',
        ]);
    }
}
