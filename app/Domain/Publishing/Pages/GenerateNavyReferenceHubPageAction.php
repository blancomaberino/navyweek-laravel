<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;

/**
 * Generates `/navy-reference/` — the reference library landing page that the
 * "← Navy Reference" back link on every reference page points at. It aggregates
 * the pillars at render (counts are live), so it owns no pageable.
 */
final class GenerateNavyReferenceHubPageAction
{
    private const LAST_REVIEWED = '2026-07-23';

    public function __construct(private readonly PageRepositoryInterface $pages) {}

    /**
     * @return int always 1 (the single /navy-reference/ page)
     */
    public function __invoke(): int
    {
        $this->pages->upsertPillarPage('navy-reference-hub', PagePaths::root('navy_reference'), [
            'page_type' => PageType::NavyReferenceHub,
            'slug' => 'navy-reference',
            'title' => 'U.S. Navy Reference — Bases, Ranks, Ratings & Designators | NavyWeek.org',
            'h1' => 'NAVY REFERENCE',
            'meta_description' => 'General U.S. Navy reference material — separate from Navy Week event coverage. Background on bases, ranks, officer designators, enlisted ratings, and veteran benefits.',
            'og_image_path' => '/og/navy-reference.png',
            'last_reviewed' => self::LAST_REVIEWED,
            'sources_checked' => self::LAST_REVIEWED,
            'trust_page_label' => 'Navy Reference library',
            'editorial_source_priority' => 'We cite navy.mil, MyNavyHR / MILPERSMAN, CNIC, and DFAS first; the U.S. Code (Title 10) and eCFR where statutes apply. Non-government sources are not used as primary evidence on these pages.',
            'editorial_review_cadence' => 'Reference material is re-verified quarterly and at every page update.',
            'is_published' => true,
        ]);

        return 1;
    }
}
