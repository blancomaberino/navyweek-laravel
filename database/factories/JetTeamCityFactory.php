<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\JetTeamStatus;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JetTeamCity>
 */
class JetTeamCityFactory extends Factory
{
    protected $model = JetTeamCity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->unique()->city();
        $slug = str($city)->slug()->value();

        return [
            'jet_team_id' => JetTeam::factory(),
            'slug' => $slug,
            'city' => $city,
            'state' => 'AK',
            'state_name' => 'Alaska',
            'year' => 2026,
            'show' => 'Arctic Thunder Air Show & Open House',
            'venue' => 'Joint Base Elmendorf-Richardson (JBER)',
            'admission' => Admission::Free,
            'dates_label' => 'August 8–9, 2026',
            'start_date' => '2026-08-08',
            'end_date' => '2026-08-09',
            'second_dates_label' => null,
            'second_start_date' => null,
            'second_end_date' => null,
            'status' => JetTeamStatus::Scheduled,
            // Defaults to published; use ->unpublished() for a draft guide.
            'published' => true,
            'needs_verification' => [],
            'hero_dateline' => 'Aug 8–9, 2026 · JBER · FREE',
            'dek' => 'The Blue Angels headline Arctic Thunder.',
            'intro' => ['A lead paragraph.'],
            'quick_facts' => [['label' => 'Admission', 'value' => 'Free']],
            'sections' => [[
                'heading' => 'Why it is special',
                'paragraphs' => ['One of the largest shows in Alaska.'],
                'bullets' => ['Free admission'],
            ]],
            'related_paragraph' => [['label' => 'Thunderbirds', 'href' => '/thunderbirds/']],
            'card_summary' => 'The Blue Angels in Anchorage.',
            'meta_title' => $city.' Blue Angels 2026',
            'meta_description' => 'Visitor guide to the Blue Angels in '.$city.'.',
            'h1' => $city.' Air Show 2026',
            'og_image' => '/og/blue-angels/'.$slug.'.png',
            'date_published' => '2026-06-10',
            'date_modified' => '2026-06-10',
            'last_verified' => 'June 10, 2026',
        ];
    }

    public function unpublished(): self
    {
        return $this->state(fn (): array => ['published' => false]);
    }

    /** A city the team visits twice in one season (adds the second window). */
    public function withSecondWindow(): self
    {
        return $this->state(fn (): array => [
            'second_dates_label' => 'November 7–8, 2026',
            'second_start_date' => '2026-11-07',
            'second_end_date' => '2026-11-08',
        ]);
    }
}
