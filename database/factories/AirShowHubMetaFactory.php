<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Models\AirShowHubMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AirShowHubMeta>
 */
class AirShowHubMetaFactory extends Factory
{
    protected $model = AirShowHubMeta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_path' => '/air-show',
            'year' => 2026,
            'eyebrow' => '// U.S. Military Air Shows',
            'hub_title' => 'Air Shows',
            'hub_subtitle' => '2026 Season',
            'seo_headline' => '2026 U.S. Military Air Shows',
            'intro' => ['A lead paragraph for the hub.'],
            'key_facts' => [['label' => 'Shows', 'value' => 'Dozens nationwide']],
            'about' => ['About the air-show circuit.'],
            'meta_title' => 'U.S. Military Air Shows 2026',
            'meta_description' => 'A directory of major U.S. military air shows.',
            'og_image' => '/og/air-show/hub.png',
            'date_published' => '2026-06-10',
            'date_modified' => '2026-06-10',
            'last_verified' => 'June 10, 2026',
        ];
    }
}
