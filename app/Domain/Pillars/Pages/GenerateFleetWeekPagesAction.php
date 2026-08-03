<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Repositories\FleetWeekRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;
use Illuminate\Support\Carbon;

/**
 * Generates the fleet-week `pages`: one page per city (`/fleetweek/{slug}/`, pageable
 * → FleetWeek) plus the hub (`/fleetweek/`, no pageable). Fleet weeks have no publish
 * gate (all cities render; Tier-3 cities simply omit the Festival node). The hub's
 * title/description/dates and FAQs are the legacy hardcoded hub constants — the FAQs
 * are seeded onto the hub page's polymorphic `faqs`.
 */
final class GenerateFleetWeekPagesAction
{
    private const HUB_PUBLISHED = '2026-06-10';

    private const HUB_TITLE = 'U.S. Fleet Week Guide: Dates, Air Shows & Ship Tours by City | NavyWeek.org';

    private const HUB_DESCRIPTION = 'Plan your visit to U.S. Fleet Week — city-by-city guides with dates, Blue Angels schedules, Parade of Ships, free ship tours, and where to watch.';

    /**
     * The legacy FleetWeekHub HUB_FAQS, ported verbatim (schema ↔ content parity).
     *
     * @var list<array{question: string, answer: string}>
     */
    private const HUB_FAQS = [
        [
            'question' => 'What is Fleet Week?',
            'answer' => 'Fleet Week is a U.S. tradition in which active Navy, Marine Corps, and Coast Guard ships visit a host city for about a week. The public can tour the ships for free, watch a Parade of Ships, and — in air-show cities like San Francisco — see flight demonstrations by teams such as the Blue Angels. Each event is run by a local organizing association, not by NavyWeek.org.',
        ],
        [
            'question' => 'Which U.S. cities have a Fleet Week?',
            'answer' => 'The best-known fleet weeks are in San Francisco, New York, and Los Angeles, with other ship-tour and tall-ship events in San Diego, Seattle (Seafair), Portland (Rose Festival), Norfolk, Baltimore, Boston, New Orleans, and Houston (hosted at Galveston). Some cities people search for — including Philadelphia, Chicago, and Miami/Fort Lauderdale — have deep Navy ties or a big air show but no traditional ship-tour fleet week. This directory documents each one and links to the organizer’s official site.',
        ],
        [
            'question' => 'Are Fleet Week events free?',
            'answer' => 'Watching the air show and Parade of Ships from public waterfront areas is free, and ship tours are typically free as well. Some cities sell optional premium or reserved seating through the organizing association, but you never have to pay to take part.',
        ],
        [
            'question' => 'Is NavyWeek.org affiliated with these events?',
            'answer' => 'No. NavyWeek.org is an independent guide. Each fleet week is organized by its own local association, and we are not affiliated with, endorsed by, or sponsored by those organizers or the U.S. Navy. We link to each official site so you can confirm current dates and details before you travel.',
        ],
    ];

    public function __construct(
        private readonly FleetWeekRepositoryInterface $fleetWeeks,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of fleet-week pages generated (cities + hub)
     */
    public function __invoke(): int
    {
        $count = 0;

        foreach ($this->fleetWeeks->all() as $week) {
            $this->pages->upsertPillarPage("fleetweek:{$week->slug}", PagePaths::child('fleet_weeks', $week->slug), [
                'page_type' => PageType::FleetWeek,
                'slug' => $week->slug,
                'title' => $week->meta_title,
                'meta_description' => $week->meta_description,
                'og_type' => 'article',
                'og_image_path' => $week->og_image,
                'date_published' => $week->date_published,
                'date_modified' => $week->date_modified,
                'is_published' => true,
            ], $week);
            $count++;
        }

        $hub = $this->pages->upsertPillarPage('fleetweek-hub', PagePaths::root('fleet_weeks'), [
            'page_type' => PageType::FleetWeek,
            'slug' => 'fleetweek',
            'title' => self::HUB_TITLE,
            'meta_description' => self::HUB_DESCRIPTION,
            'og_image_path' => '/og/fleetweek/hub.png',
            'date_published' => Carbon::parse(self::HUB_PUBLISHED),
            'date_modified' => Carbon::parse(self::HUB_PUBLISHED),
            'is_published' => true,
        ]);
        $this->seedHubFaqs($hub);
        $count++;

        return $count;
    }

    /** Idempotently (re)seed the hub page's FAQs from the ported HUB_FAQS constant. */
    private function seedHubFaqs(Page $page): void
    {
        $page->replaceFaqs(array_map(static fn (array $faq, int $i): array => [
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'sort_order' => $i + 1,
        ], self::HUB_FAQS, array_keys(self::HUB_FAQS)));
    }
}
