<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\TeamId;
use App\Domain\Pillars\Models\JetTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JetTeam>
 */
class JetTeamFactory extends Factory
{
    protected $model = JetTeam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Defaults to the Blue Angels; use ->thunderbirds() for the other team.
        return [
            'team' => TeamId::BlueAngels,
            'name' => 'Blue Angels',
            'full_name' => 'U.S. Navy Blue Angels',
            'branch' => 'U.S. Navy',
            'aircraft' => 'F/A-18 Super Hornet',
            'home_base' => 'NAS Pensacola, Florida',
            'base_path' => '/blue-angels',
            'year' => 2026,
            'eyebrow' => '// U.S. Navy Flight Demonstration Squadron',
            'hub_title' => 'BLUE ANGELS',
            'hub_subtitle' => '2026 Schedule',
            'seo_headline' => 'Blue Angels 2026 Schedule: All Air Show Locations',
            'intro' => ['The Blue Angels 2026 season.'],
            'key_facts' => [['label' => 'Aircraft', 'value' => 'F/A-18 Super Hornet']],
            'about' => ['About the Blue Angels.'],
            'cross_team' => ['label' => 'See the Thunderbirds', 'href' => '/thunderbirds/'],
            'meta_title' => 'Blue Angels 2026 Schedule',
            'meta_description' => 'The full Blue Angels 2026 air show schedule.',
            'og_image' => '/og/blue-angels/hub.png',
            'date_published' => '2026-06-10',
            'date_modified' => '2026-06-10',
            'last_verified' => 'June 10, 2026',
        ];
    }

    public function thunderbirds(): self
    {
        return $this->state(fn (): array => [
            'team' => TeamId::Thunderbirds,
            'name' => 'Thunderbirds',
            'full_name' => 'U.S. Air Force Thunderbirds',
            'branch' => 'U.S. Air Force',
            'aircraft' => 'F-16 Fighting Falcon',
            'home_base' => 'Nellis AFB, Nevada',
            'base_path' => '/thunderbirds',
            'eyebrow' => '// U.S. Air Force Air Demonstration Squadron',
            'hub_title' => 'THUNDERBIRDS',
            'seo_headline' => 'Thunderbirds 2026 Schedule: All Air Show Locations',
            'intro' => ['The Thunderbirds 2026 season.'],
            'key_facts' => [['label' => 'Aircraft', 'value' => 'F-16 Fighting Falcon']],
            'about' => ['About the Thunderbirds.'],
            'cross_team' => ['label' => 'See the Blue Angels', 'href' => '/blue-angels/'],
            'meta_title' => 'Thunderbirds 2026 Schedule',
            'meta_description' => 'The full Thunderbirds 2026 air show schedule.',
            'og_image' => '/og/thunderbirds/hub.png',
        ]);
    }
}
