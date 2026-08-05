<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Pillars\Repositories\NavyWeekEventRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use Illuminate\Support\Carbon;

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

    /** The legacy `meta.lastChecked` — drives "Last verified" on both pages. */
    private const LAST_CHECKED = '2026-07-12';

    public function __construct(
        private readonly NavyWeekEventRepositoryInterface $events,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of pages generated (always 2)
     */
    public function __invoke(): int
    {
        $all = $this->events->all()->sortBy('sequence')->values();
        $count = $all->count();
        $year = self::YEAR;
        $firstTime = $all->filter(fn ($e) => $e->first_time || filled($e->first_time_location))->count();
        $label = static function (?NavyWeekEvent $e): string {
            if ($e === null) {
                return '';
            }
            $range = $e->start_date->format('M d').' – '.$e->end_date->format('M d, Y');

            return "{$e->city}, {$e->state_abbr} ({$range})";
        };
        $firstLabel = $label($all->first());
        $lastLabel = $label($all->last());

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
                    ['label' => 'Total host cities', 'value' => (string) $count],
                    ['label' => 'First-time locations', 'value' => (string) $firstTime],
                    ['label' => 'First stop', 'value' => $firstLabel],
                    ['label' => 'Final stop', 'value' => $lastLabel],
                    ['label' => 'Events per city', 'value' => '75–100 free, public-facing events'],
                    ['label' => 'Theme', 'value' => '"Road Trip to 250" — celebrating the 250th birthday of the U.S. Navy and the United States'],
                ],
                'source' => ['label' => 'outreach.navy.mil/Navy-Weeks', 'url' => 'https://outreach.navy.mil/Navy-Weeks/'],
                'lastVerified' => Carbon::parse(self::LAST_CHECKED)->format('F j, Y'),
            ],
            'last_reviewed' => self::LAST_CHECKED,
            'sources_checked' => self::LAST_CHECKED,
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
                'source' => ['label' => 'outreach.navy.mil/Navy-Weeks', 'url' => 'https://outreach.navy.mil/Navy-Weeks/'],
                'lastVerified' => Carbon::parse(self::LAST_CHECKED)->format('F j, Y'),
            ],
            'last_reviewed' => self::LAST_CHECKED,
            'sources_checked' => self::LAST_CHECKED,
            'is_published' => true,
        ]);

        return 2;
    }
}
