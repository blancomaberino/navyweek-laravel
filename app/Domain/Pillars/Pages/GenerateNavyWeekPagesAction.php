<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Repositories\NavyWeekEventRepositoryInterface;
use App\Domain\Pillars\Seo\NavyWeekCitySchema;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;
use Illuminate\Support\Carbon;

/**
 * Generates the Navy Week city `pages` — one per event (`/city/{slug}/`, pageable →
 * NavyWeekEvent). No publish gate (every city renders). Title + meta description are
 * derived by NavyWeekCitySchema (the legacy CityDetail seoTitle / buildCityMetaDescription).
 * City pages carry no editorial byline (no Person nodes in the graph); dates follow the
 * build clock. Idempotent — upserts by url_path.
 */
final class GenerateNavyWeekPagesAction
{
    public function __construct(
        private readonly NavyWeekEventRepositoryInterface $events,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of city pages generated
     */
    public function __invoke(): int
    {
        $now = Carbon::now();
        $count = 0;

        foreach ($this->events->all() as $event) {
            $this->pages->upsertPillarPage("navy-week-city:{$event->slug}", PagePaths::child('navy_week_cities', $event->slug), [
                'page_type' => PageType::NavyWeekCity,
                'slug' => $event->slug,
                'title' => NavyWeekCitySchema::metaTitle($event),
                'meta_description' => NavyWeekCitySchema::metaDescription($event),
                'og_type' => 'article',
                'og_image_path' => "/og/{$event->slug}.png",
                'date_published' => $now,
                'date_modified' => $now,
                'is_published' => true,
            ], $event);
            $count++;
        }

        return $count;
    }
}
