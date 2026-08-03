<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

/**
 * Seeds the `/veterans-day/free-meals/` roundup page — a data-driven page whose body
 * (the offers table) and JSON-LD ItemList/FAQ are computed at render from the
 * `veterans_day_meals` aggregate, so this action only owns the routing/SEO `pages`
 * row. Ported from the legacy `src/page-views/VeteransDayFreeMeals.tsx` seo export.
 *
 * A `Static` page dispatched by slug (`veterans-day-free-meals`) — the same pattern
 * as the `/discount/` directory — so no new PageType/match arm is needed. Idempotent:
 * upserts by the stable `generation_key`; a genuinely-fixed one-off path.
 */
final class GenerateVeteransDayFreeMealsPageAction
{
    private const URL_PATH = '/veterans-day/free-meals/';

    private const GENERATION_KEY = 'content:veterans-day-free-meals';

    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function __invoke(): void
    {
        $this->pages->upsertPillarPage(self::GENERATION_KEY, self::URL_PATH, [
            'page_type' => PageType::Static,
            'slug' => 'veterans-day-free-meals',
            'title' => 'Veterans Day Free Meals 2026: Verified Restaurant Offers | NavyWeek.org',
            'meta_description' => 'Every Veterans Day 2026 free meal for veterans and service members — verified offers, each dated. See who qualifies, dine-in vs takeout, and the official source.',
            'og_image_path' => '/og/veterans-day.png',
            'date_published' => '2026-06-29',
            'date_modified' => '2026-06-29',
            'is_published' => true,
        ]);
    }
}
