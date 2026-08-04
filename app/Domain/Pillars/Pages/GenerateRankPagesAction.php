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
    /** The legacy hubs' LAST_REVIEWED_LABEL (July 23, 2026), ported verbatim. */
    private const REFERENCE_LAST_REVIEWED = '2026-07-23';

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
            'h1' => 'NAVY RANKS',
            'meta_description' => "Every U.S. Navy rank on one page — {$commissioned} commissioned officer (O-1 to O-10), {$warrant} warrant officer (W-1 to W-5), and {$enlisted} enlisted (E-1 to E-9) paygrades with insignia, abbreviations, and NATO codes.",
            'og_image_path' => '/og/ranks/hub.png',
            'date_published' => $now,
            'date_modified' => $now,
            'last_reviewed' => self::REFERENCE_LAST_REVIEWED,
            'sources_checked' => self::REFERENCE_LAST_REVIEWED,
            'shows_reference_backlink' => true,
            'key_facts' => [
                'title' => 'U.S. Navy Ranks — Key Facts',
                'facts' => [
                    ['label' => 'Commissioned officer ranks', 'value' => "{$commissioned} (O-1 Ensign → O-10 Admiral)"],
                    ['label' => 'Warrant officer ranks', 'value' => "{$warrant} (W-1 → W-5)"],
                    ['label' => 'Enlisted paygrades', 'value' => "{$enlisted} (E-1 Seaman Recruit → E-9 Master Chief Petty Officer)"],
                    ['label' => 'Top enlisted billet', 'value' => 'Master Chief Petty Officer of the Navy (MCPON)'],
                    ['label' => 'Highest active rank', 'value' => 'Admiral (O-10) — the Chief of Naval Operations and select fleet commanders'],
                ],
                'source' => ['label' => 'U.S. Navy — Ranks & Insignia (navy.mil)', 'url' => 'https://www.navy.mil/About/Ranks-and-Insignia/'],
            ],
            'trust_page_label' => 'Navy Ranks reference hub',
            'editorial_source_priority' => 'We cite navy.mil insignia plates, MyNavyHR / MILPERSMAN, and DFAS basic pay tables first; the U.S. Code (Title 10) and eCFR where statutes apply. Non-government sources are not used as primary evidence on this page.',
            'editorial_review_cadence' => 'Insignia, NATO codes, and rank structure are re-verified quarterly and at every page update.',
            'is_published' => true,
        ]);

        $this->pages->upsertPillarPage('rating-list', PagePaths::root('ratings'), [
            'page_type' => PageType::Rating,
            'slug' => 'navy-ratings',
            'title' => "U.S. Navy Ratings — All {$active} Active Enlisted Ratings Listed | NavyWeek.org",
            'h1' => 'NAVY RATINGS',
            'meta_description' => "Every U.S. Navy enlisted rating on one page — all {$active} active ratings grouped by community, plus {$historic} historic (disestablished) ratings, with rating badges and abbreviations.",
            'og_image_path' => '/og/default.png',
            'date_published' => $now,
            'date_modified' => $now,
            'last_reviewed' => self::REFERENCE_LAST_REVIEWED,
            'sources_checked' => self::REFERENCE_LAST_REVIEWED,
            'shows_reference_backlink' => true,
            'key_facts' => [
                'title' => 'U.S. Navy Ratings — Key Facts',
                'facts' => [
                    ['label' => 'Active enlisted ratings', 'value' => (string) $active],
                    ['label' => 'Historic (disestablished) ratings', 'value' => (string) $historic],
                    ['label' => 'What a rating is', 'value' => "A Navy enlisted job specialty — the sailor's occupational field, worn as a rating badge"],
                    ['label' => 'Rating vs. rank', 'value' => 'A rating is the job; the paygrade (E-1 to E-9) is the rank'],
                ],
                'source' => ['label' => 'U.S. Navy — Ranks & Insignia (navy.mil)', 'url' => 'https://www.navy.mil/About/Ranks-and-Insignia/'],
            ],
            'trust_page_label' => 'Navy Ratings reference hub',
            'editorial_source_priority' => 'We cite navy.mil rating badge plates, MyNavyHR / MILPERSMAN, and NAVPERS rating documentation first; the U.S. Code (Title 10) and eCFR where statutes apply. Non-government sources are not used as primary evidence on this page.',
            'editorial_review_cadence' => 'Rating badges, abbreviations, and community groupings are re-verified quarterly and at every page update.',
            'is_published' => true,
        ]);

        return 2;
    }
}
