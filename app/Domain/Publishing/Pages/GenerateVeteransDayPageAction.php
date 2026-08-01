<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

/**
 * Seeds the `/veterans-day/` content page — a DB-driven editorial page (body in
 * `body_blocks`, FAQs on the polymorphic `faqs`), migrated VERBATIM from the legacy
 * `src/page-views/VeteransDay.tsx`. Its JSON-LD is Article + author Person + FAQPage
 * (built by `ContentPageSchema` with `emitFaqPage`). Idempotent: upserts by url_path
 * and does NOT clobber an editor's body/FAQs on re-run.
 */
final class GenerateVeteransDayPageAction
{
    private const URL_PATH = '/veterans-day/';

    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function __invoke(): void
    {
        $existing = $this->pages->findPublishedByPath(self::URL_PATH);
        $isNew = $existing === null || $existing->body_blocks === null || $existing->body_blocks === [];

        $attributes = [
            'page_type' => PageType::VeteransDayHub,
            'slug' => 'veterans-day',
            'title' => 'Veterans Day 2026: History, Meaning & How the Navy Observes It | NavyWeek.org',
            'meta_description' => 'Veterans Day 2026 is Wednesday, November 11. Its history from Armistice Day, how it differs from Memorial Day, and how the U.S. Navy observes it.',
            'og_image_path' => '/og/veterans-day.png',
            'date_published' => '2026-06-02',
            'date_modified' => '2026-06-02',
            'is_published' => true,
        ];
        if ($isNew) {
            $attributes['body_blocks'] = $this->bodyBlocks();
        }

        $page = $this->pages->upsertPillarPage(self::URL_PATH, $attributes);

        if ($isNew || $page->faqs()->count() === 0) {
            $page->replaceFaqs($this->faqs());
        }
    }

    /**
     * @return list<array{type: string, text?: string, items?: list<string>}>
     */
    private function bodyBlocks(): array
    {
        return [
            ['type' => 'heading', 'text' => 'What Veterans Day is'],
            ['type' => 'paragraph', 'text' => 'Veterans Day is a U.S. federal holiday held on November 11 each year to honor everyone who has served in the United States armed forces — the Navy, Marine Corps, Army, Air Force, Space Force, and Coast Guard — whether in war or peacetime, and whether they are living or have passed away (VA.gov — Veterans Day).'],
            ['type' => 'paragraph', 'text' => 'Unlike many federal holidays that move to a Monday, Veterans Day stays anchored to November 11. The date itself is the point: it commemorates the armistice that ended the fighting of World War I at 11:00 a.m. on November 11, 1918.'],
            ['type' => 'note', 'text' => 'Spelling note. The federal government writes it as "Veterans Day" — no apostrophe. It is a day for honoring all veterans, not a day that belongs to them, so the possessive apostrophe is intentionally omitted.'],

            ['type' => 'heading', 'text' => 'History & origins'],
            ['type' => 'paragraph', 'text' => 'The holiday began as Armistice Day. President Woodrow Wilson first proclaimed November 11, 1919, to commemorate the first anniversary of the end of World War I (VA.gov — History of Veterans Day).'],
            ['type' => 'paragraph', 'text' => 'In 1938, Congress made November 11 a legal federal holiday "dedicated to the cause of world peace and to be hereafter celebrated and known as \'Armistice Day.\'" After World War II and the Korean War, veterans service organizations urged Congress to broaden the day to honor all American veterans. In 1954, President Dwight D. Eisenhower signed legislation changing the name from Armistice Day to Veterans Day.'],
            ['type' => 'paragraph', 'text' => 'For a few years the date moved: the Uniform Monday Holiday Act of 1968 shifted Veterans Day to the fourth Monday in October beginning in 1971. The change proved unpopular — many states kept observing November 11 — and in 1975 President Gerald Ford signed a law returning Veterans Day to its original November 11 date, effective 1978, where it has remained ever since.'],

            ['type' => 'heading', 'text' => 'Veterans Day vs. Memorial Day vs. Armed Forces Day'],
            ['type' => 'paragraph', 'text' => 'These three observances are often confused. Each honors a different group:'],
            ['type' => 'list', 'items' => [
                'Veterans Day — November 11 — All who served (living & deceased)',
                'Memorial Day — Last Monday in May — Those who died in service',
                'Armed Forces Day — Third Saturday in May — Those currently serving',
            ]],
            ['type' => 'note', 'text' => 'Quick way to remember. Memorial Day remembers the fallen; Veterans Day thanks all who served; Armed Forces Day recognizes those serving right now.'],

            ['type' => 'heading', 'text' => 'How the U.S. Navy observes Veterans Day'],
            ['type' => 'paragraph', 'text' => 'Across the fleet, the Navy marks Veterans Day with ceremonies at bases, aboard ships, and in communities. Typical observances include wreath-layings at memorials, color guard presentations, moments of silence, and Navy band performances. Current Sailors frequently join veterans at local parades and ceremonies (Navy.mil).'],
            ['type' => 'paragraph', 'text' => "The Navy Office of Community Outreach (NAVCO) coordinates much of the Navy's public engagement, including the Navy Week program that brings Sailors and Navy assets to cities without a major Navy presence (outreach.navy.mil). For the historical record of the sea service, the Naval History and Heritage Command preserves the stories of the Sailors Veterans Day honors."],

            ['type' => 'heading', 'text' => 'Veterans Day 2026 & Navy Week'],
            ['type' => 'paragraph', 'text' => 'In 2026, Veterans Day falls squarely within a Navy Week. Flagstaff Navy Week runs November 9–16, 2026 — the grand finale of the Navy\'s nationwide "Road Trip to 250" — so Veterans Day on November 11 lands in the middle of the week\'s events (NAVCO — Flagstaff 2026).'],
            ['type' => 'paragraph', 'text' => 'That makes Flagstaff an especially fitting place to honor veterans in 2026: the Navy operates the Naval Observatory Flagstaff Station nearby, and the region is home to hundreds of thousands of Arizona veterans and the heritage of the WWII Navajo Code Talkers. See the Flagstaff Navy Week guide for details, or browse the full 2026 Navy Week schedule.'],

            ['type' => 'heading', 'text' => 'Ways to honor & participate'],
            ['type' => 'note', 'text' => "Veterans Day free meals. Hundreds of restaurants thank veterans and service members with a free meal on November 11. See our verified Veterans Day Free Meals 2026 list — every offer checked against the brand's own official source."],
            ['type' => 'list', 'items' => [
                'Attend a ceremony or parade. Most communities hold a Veterans Day observance — many on or near November 11. Local VFW and American Legion posts are good places to find one.',
                "Thank a veteran. A simple, sincere acknowledgment of a veteran's service is meaningful — at work, in your family, or in your neighborhood.",
                'Fly the flag. Display the U.S. flag according to the Flag Code (VA flag display guide).',
                'Support veteran organizations. Reputable veterans service organizations provide claims help, transition support, and community for those who served.',
                'Volunteer. VA medical centers and national cemeteries welcome volunteers, especially around Veterans Day.',
                'Visit a memorial or cemetery. Many people lay flowers or a wreath at a local memorial or national cemetery to mark the day.',
            ]],

            ['type' => 'heading', 'text' => 'Official resources'],
            ['type' => 'list', 'items' => [
                'VA.gov — Veterans Day: https://www.va.gov/opa/vetsday/',
                'VA.gov — History of Veterans Day: https://www.va.gov/opa/vetsday/vetdayhistory.asp',
                'OPM — Federal holidays: https://www.opm.gov/policy-data-oversight/pay-leave/federal-holidays/',
                'Navy.mil: https://www.navy.mil/',
                'Naval History and Heritage Command: https://www.history.navy.mil/',
                'NAVCO — Navy outreach & Navy Weeks: https://outreach.navy.mil/',
            ]],
        ];
    }

    /**
     * The 9 FAQs, verbatim from the legacy page.
     *
     * @return list<array{question: string, answer: string, sort_order: int}>
     */
    private function faqs(): array
    {
        $rows = [
            ['When is Veterans Day 2026?', 'Veterans Day 2026 is observed on Wednesday, November 11, 2026. Veterans Day is always commemorated on November 11 each year, regardless of the day of the week, because the date marks the anniversary of the 1918 Armistice that ended the fighting in World War I.'],
            ['Why is Veterans Day on November 11?', 'The fighting of World War I ended with an armistice that took effect at the 11th hour of the 11th day of the 11th month — 11:00 a.m. on November 11, 1918. The United States first marked the date as Armistice Day in 1919, Congress made it an annual observance in 1938, and in 1954 it was renamed Veterans Day to honor veterans of all American wars.'],
            ["What's the difference between Veterans Day and Memorial Day?", 'Veterans Day (November 11) honors all who have served in the U.S. armed forces — living and deceased. Memorial Day (the last Monday in May) specifically honors service members who died while serving. A simple way to remember it: Memorial Day remembers those who gave their lives; Veterans Day thanks all who served.'],
            ['How is Veterans Day different from Armed Forces Day?', 'Armed Forces Day (the third Saturday in May) honors people who are currently serving on active duty. Veterans Day honors those who have served and are no longer on active duty, while Memorial Day honors those who died in service. Together the three observances cover current members, former members, and the fallen.'],
            ['Is Veterans Day a federal holiday?', 'Yes. Veterans Day is one of the eleven U.S. federal holidays. Federal offices are generally closed, and many schools, banks, and businesses observe it as well. When November 11 falls on a weekend, federal employees typically receive the adjacent Friday or Monday as the observed day off — but the commemoration itself stays on November 11.'],
            ['How does the U.S. Navy observe Veterans Day?', 'The Navy marks Veterans Day with wreath-laying ceremonies, ship and base observances, community events, and outreach through the Navy Office of Community Outreach (NAVCO). Navy bands perform, color guards present the colors, and current Sailors join veterans at memorials and parades nationwide to recognize those who served.'],
            ['Is there a Navy Week happening around Veterans Day 2026?', 'Yes. Flagstaff Navy Week runs November 9–16, 2026 — the grand finale of the 2026 "Road Trip to 250" — and includes Veterans Day on November 11. It is a natural opportunity to connect with Sailors, attend Navy band performances, and honor veterans in person.'],
            ['What are good ways to honor veterans on Veterans Day?', 'You can attend a local Veterans Day ceremony or parade, thank a veteran in your life, fly the U.S. flag, support reputable veteran service organizations, volunteer at a VA medical center, or visit a national cemetery or memorial. Many businesses also offer recognition to veterans on the day.'],
            ['Is Veterans Day spelled with an apostrophe?', 'No. The U.S. Department of Veterans Affairs uses "Veterans Day" without an apostrophe. The holiday is not a day that "belongs to" veterans (possessive); it is a day for honoring all veterans (attributive), so no apostrophe is used.'],
        ];

        $faqs = [];
        foreach ($rows as $i => [$question, $answer]) {
            $faqs[] = ['question' => $question, 'answer' => $answer, 'sort_order' => $i];
        }

        return $faqs;
    }
}
