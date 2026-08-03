<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Repositories\RankRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;

/**
 * Generates the two consolidated reference-list `pages`: `/navy-ranks/` and
 * `/navy-ratings/`. Each lists every rank / rating on one page (per-entry anchors,
 * no per-entry routes), so the page owns no single `pageable` (null). Title and
 * meta description mirror the legacy hubs verbatim, with the entry counts computed
 * live from the DB so they never drift. Idempotent — upserts by url_path; the build
 * clock (date_published set once, preserved) lives in the repository.
 */
final class GenerateRankPagesAction
{
    public function __construct(
        private readonly RankRepositoryInterface $ranks,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of list pages generated (always 2)
     */
    public function __invoke(): int
    {
        $now = now();

        $commissioned = $this->ranks->forCategoryByPaygrade(RankCategory::OfficerCommissioned)->count();
        $warrant = $this->ranks->forCategoryByPaygrade(RankCategory::OfficerWarrant)->count();
        $enlisted = $this->ranks->forCategoryByPaygrade(RankCategory::EnlistedPaygrade)->count();
        $active = $this->ranks->activeRatings()->count();
        $historic = $this->ranks->historicRatings()->count();

        $this->pages->upsertPillarPage('rank-list', PagePaths::root('ranks'), [
            'page_type' => PageType::Rank,
            'slug' => 'navy-ranks',
            'title' => 'U.S. Navy Ranks — Every Officer & Enlisted Rank Listed | NavyWeek.org',
            'meta_description' => "Every U.S. Navy rank on one page — {$commissioned} commissioned officer (O-1 to O-10), {$warrant} warrant officer (W-1 to W-5), and {$enlisted} enlisted (E-1 to E-9) paygrades with insignia, abbreviations, and NATO codes.",
            'og_image_path' => '/og/ranks/hub.png',
            'date_published' => $now,
            'date_modified' => $now,
            'is_published' => true,
        ]);

        $this->pages->upsertPillarPage('rating-list', PagePaths::root('ratings'), [
            'page_type' => PageType::Rating,
            'slug' => 'navy-ratings',
            'title' => "U.S. Navy Ratings — All {$active} Active Enlisted Ratings Listed | NavyWeek.org",
            'meta_description' => "Every U.S. Navy enlisted rating on one page — all {$active} active ratings grouped by community, plus {$historic} historic (disestablished) ratings, with rating badges and abbreviations.",
            'og_image_path' => '/og/default.png',
            'date_published' => $now,
            'date_modified' => $now,
            'is_published' => true,
        ]);

        return 2;
    }
}
