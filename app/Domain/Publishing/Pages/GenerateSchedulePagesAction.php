<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Pillars\Repositories\NavyWeekEventRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

/**
 * Generates the two Navy Week programme pages: `/schedule/` (the full 2026
 * schedule) and `/map/` (the route map + tour stops). Both aggregate the
 * navy-week pillar at render, so neither owns a pageable.
 *
 * These are one-off pages, not families — they own their fixed paths (a reviewed
 * opt-out recorded in PagePathHygieneTest's allowlist).
 */
final class GenerateSchedulePagesAction
{
    private const YEAR = 2026;

    public function __construct(
        private readonly NavyWeekEventRepositoryInterface $events,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of pages generated (always 2)
     */
    public function __invoke(): int
    {
        $count = $this->events->all()->count();
        $year = self::YEAR;

        $this->pages->upsertPillarPage('schedule', '/schedule/', [
            'page_type' => PageType::Schedule,
            'slug' => 'schedule',
            'title' => "Navy Week {$year} Schedule — All {$count} Cities & Dates | NavyWeek.org",
            'h1' => "{$year} SCHEDULE",
            'meta_description' => "The full Navy Week {$year} schedule — all {$count} host cities with dates, anchor events, and what the Navy brings to each stop.",
            'og_image_path' => '/og/schedule.png',
            'key_facts' => [
                'title' => "{$year} Schedule — Key Facts",
                'facts' => [
                    ['label' => 'Host cities', 'value' => (string) $count],
                    ['label' => 'Programme', 'value' => "U.S. Navy Week {$year} — the Navy's flagship community outreach tour"],
                    ['label' => 'Operator', 'value' => 'Navy Office of Community Outreach (NAVCO), Millington, Tennessee'],
                    ['label' => 'Cost to attend', 'value' => 'Free — every Navy Week event is open to the public at no charge'],
                ],
                'source' => ['label' => 'Navy Office of Community Outreach (outreach.navy.mil)', 'url' => 'https://outreach.navy.mil/Navy-Weeks/'],
            ],
            'is_published' => true,
        ]);

        $this->pages->upsertPillarPage('route-map', '/map/', [
            'page_type' => PageType::RouteMap,
            'slug' => 'map',
            'title' => "Navy Week {$year} Route Map — Every Tour Stop | NavyWeek.org",
            'h1' => "{$year} ROUTE MAP",
            'meta_description' => "The Navy Week {$year} route map — every tour stop plotted, with dates and the anchor event in each host city.",
            'og_image_path' => '/og/map.png',
            'key_facts' => [
                'title' => "{$year} Route Map — Key Facts",
                'facts' => [
                    ['label' => 'Tour stops', 'value' => (string) $count],
                    ['label' => 'Programme year', 'value' => "{$year} — the \"Road Trip to 250\" tour"],
                    ['label' => 'Operator', 'value' => 'Navy Office of Community Outreach (NAVCO)'],
                ],
                'source' => ['label' => 'Navy Office of Community Outreach (outreach.navy.mil)', 'url' => 'https://outreach.navy.mil/Navy-Weeks/'],
            ],
            'is_published' => true,
        ]);

        return 2;
    }
}
