<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\AirShowStatus;
use App\Domain\Pillars\Models\AirShow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AirShow>
 */
class AirShowFactory extends Factory
{
    protected $model = AirShow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shortName = fake()->unique()->city();
        $slug = str($shortName)->slug()->value();

        return [
            'slug' => $slug,
            'short_name' => $shortName,
            'name' => $shortName.' Air Show',
            'city' => $shortName,
            'state' => 'CA',
            'state_name' => 'California',
            'year' => 2026,
            'base' => 'MCAS '.$shortName,
            'dates_label' => 'September 26–28, 2026',
            'start_date' => '2026-09-26',
            'end_date' => '2026-09-28',
            'date_unconfirmed' => false,
            'gate_time' => '8:00 a.m.',
            'admission' => Admission::Free,
            'parking' => 'Free on-base parking.',
            'headliner' => 'Blue Angels',
            'performers' => ['Blue Angels', 'F-35 Demo Team'],
            'official_url' => 'https://example.com/airshow',
            'phone' => null,
            'status' => AirShowStatus::Scheduled,
            // Defaults to published + confirmed; use ->unpublished()/->unconfirmed().
            'published' => true,
            'needs_verification' => [],
            'hero_headline' => $shortName.' Air Show 2026',
            'intro' => ['A lead paragraph.'],
            'quick_facts' => [['label' => 'Admission', 'value' => 'Free']],
            'sections' => [[
                'heading' => 'What to expect',
                'blocks' => [['kind' => 'p', 'text' => 'A full day of flying.']],
            ]],
            'related_paragraph' => [['label' => 'Miramar', 'href' => '/air-show/miramar/']],
            'card_summary' => 'A free military air show.',
            'email_cta' => null,
            'schema_name' => $shortName.' Air Show 2026',
            'event_description' => 'An annual military air show.',
            'location' => [
                'placeName' => 'MCAS '.$shortName,
                'addressLocality' => $shortName,
                'addressRegion' => 'CA',
                'addressCountry' => 'US',
            ],
            'offer' => ['name' => 'General admission', 'price' => '0', 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock'],
            'organizer' => ['name' => 'The base', 'url' => 'https://example.com'],
            'meta_title' => $shortName.' Air Show 2026',
            'meta_description' => 'Visitor guide to the '.$shortName.' Air Show.',
            'h1' => $shortName.' Air Show 2026',
            'og_image' => '/og/air-show/'.$slug.'.png',
            'canonical_override' => null,
            'date_published' => '2026-06-10',
            'date_modified' => '2026-06-10',
            'last_verified' => 'June 10, 2026',
        ];
    }

    public function unpublished(): self
    {
        return $this->state(fn (): array => ['published' => false]);
    }

    public function unconfirmed(): self
    {
        return $this->state(fn (): array => [
            'date_unconfirmed' => true,
            'start_date' => '',
            'end_date' => '',
        ]);
    }

    /** A disambiguation/router page that canonicalizes to another guide. */
    public function router(string $target = '/air-show/miramar/'): self
    {
        return $this->state(fn (): array => ['canonical_override' => $target]);
    }
}
