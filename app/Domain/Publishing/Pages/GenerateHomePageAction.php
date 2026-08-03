<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Seo\HomePageSchema;
use Illuminate\Support\Carbon;

/**
 * Seeds the home landing page (`/`) — the site root, a data-driven hub (NOT a
 * `body_blocks` CMS page): the body renders the live Navy Week schedule + key facts from
 * the `NavyWeekEvent` pillar, so there is no stored prose. What IS seeded here is the
 * `pages` row (type Home, url `/`) and the home FAQs on the page's polymorphic `faqs`
 * (port of the legacy `generalFaqs`), which feed both the rendered FAQ section and the
 * FAQPage JSON-LD ({@see HomePageSchema}).
 *
 * Idempotent: upserts by the stable `generation_key` ("content:home") so the build clock
 * preserves `date_published`, an editor's later FAQ edits are not clobbered, and a page
 * an editor renamed is still recognized. `/` is a genuine one-off — it owns its full path
 * and is not a `config('publishing.paths')` family.
 */
final class GenerateHomePageAction
{
    private const URL_PATH = '/';

    private const GENERATION_KEY = 'content:home';

    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function __invoke(): void
    {
        $now = Carbon::now();

        // upsertPillarPage keys on the stable generation_key, so it preserves an editor
        // rename and the first date_published (build clock) across re-runs.
        $page = $this->pages->upsertPillarPage(self::GENERATION_KEY, self::URL_PATH, [
            'page_type' => PageType::Home,
            'slug' => 'home',
            'title' => 'Navy Week 2026 — Free Events in 12 Cities Nationwide | NavyWeek.org',
            'meta_description' => "U.S. Navy Week 2026 brings sailors, ships, Blue Angels, and free events to 12 cities from January through November. Find your city's schedule and details.",
            'og_image_path' => '/og/home.png',
            'date_published' => $now,
            'date_modified' => $now,
            'is_published' => true,
        ]);

        // Seed the FAQs only when the page has none — a brand-new page has zero FAQs right
        // after the upsert; a page whose FAQs an editor has set is left untouched on re-run.
        if ($page->faqs()->count() === 0) {
            $page->replaceFaqs($this->faqs());
        }
    }

    /**
     * The home FAQs, verbatim from the legacy `generalFaqs` (`src/data/data.ts`).
     *
     * @return list<array{question: string, answer: string, sort_order: int}>
     */
    private function faqs(): array
    {
        $rows = [
            ['What is Navy Week?', "Navy Week is the U.S. Navy's flagship outreach program, bringing sailors, ships, aircraft, and interactive exhibits to cities across America that don't have a significant Navy presence. Running since 2005, the program is designed to show Americans how their Navy operates around the world and why a strong maritime force is vital to national security and the American way of life."],
            ['Is Navy Week free to attend?', 'Yes, all Navy Week events are completely free and open to the public. This includes Blue Angels demonstrations, Navy Band concerts, Leap Frogs parachute jumps, STEM exhibits, meet-and-greet opportunities with sailors, and ship tours at coastal stops. Some anchor events hosted by local partners may have their own admission fees.'],
            ['How many cities does Navy Week visit in 2026?', 'In 2026, Navy Week will visit 12 cities across the United States as part of the "Road Trip to 250" celebrating the nation\'s semiquincentennial. The tour starts in Rio Grande Valley, Texas in January and concludes in Flagstaff, Arizona in November. Eight of the 12 stops mark a first-time Navy Week location — Rio Grande Valley, Hilo, Lexington, the National Parks nationwide format, Harrisburg, Burlington, Sussex County, and Flagstaff — including an unprecedented nationwide format during National Park Week.'],
            ['What events happen during a typical Navy Week?', 'Each Navy Week deploys 50 to 100 sailors who participate in roughly 75 to 100 community events over about a week. Typical activities include Blue Angels flight demonstrations, U.S. Navy Band performances, Navy Leap Frogs parachute team demonstrations, Strike Group VR experiences, STEM and technology exhibits, community service projects, ship tours at coastal stops, and opportunities to meet active-duty sailors and Navy leadership.'],
            ['Can I meet sailors at Navy Week?', 'Absolutely. Meeting and interacting with active-duty sailors is one of the highlights of Navy Week. Sailors from various ratings and specialties are available throughout the week at community events, schools, civic organizations, and public venues to share their experiences and answer questions about Navy life and careers.'],
            ['What are the Blue Angels?', "The Blue Angels are the United States Navy's flight demonstration squadron, founded in 1946. They perform precision aerial maneuvers in F/A-18 Super Hornet jets at speeds up to 700 mph. In 2026, the Blue Angels performed at the Air Dot Show in Harrisburg, PA (May 23–24) and are scheduled to fly at the Yellowstone International Air Show in Billings, MT in August, during their respective Navy Weeks."],
            ['Where can I find the Navy Week schedule?', "The complete 2026 Navy Week schedule is available on this website at navyweek.org/schedule. You can view all 12 cities with their dates, anchor events, and current status. For official event details and specific daily schedules, visit the Navy's outreach website at outreach.navy.mil."],
            ['How long has Navy Week been running?', "Navy Week has been the Navy's principal community outreach program since 2005, making 2026 its 22nd year. Managed by the Navy Office of Community Outreach (NAVCO) in Millington, TN, the program has conducted over 300 Navy Weeks in 100+ U.S. markets, reaching more than 140 million Americans annually."],
        ];

        $faqs = [];
        foreach ($rows as $i => [$question, $answer]) {
            $faqs[] = ['question' => $question, 'answer' => $answer, 'sort_order' => $i];
        }

        return $faqs;
    }
}
