<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Enums\DesignatorCommunity;
use App\Domain\Pillars\Models\Rank;
use App\Domain\Pillars\Repositories\RankRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;

/**
 * Generates the officer-designator pages: the `/navy-designators/` hub, one hub
 * per community (Unrestricted Line, Restricted Line, Staff Corps), and a detail
 * page for each of the 24 designators.
 *
 * The designators were already imported as `ranks` rows (category
 * `officer-designator`) with their overview/history/FAQs — only the `pages` rows
 * were missing, so every one of these URLs fell through the catch-all to `/`.
 * Detail pages carry the Rank as their pageable; the hubs aggregate at render.
 * Idempotent: keyed on stable generation keys.
 */
final class GenerateDesignatorPagesAction
{
    /** `LAST_REVIEWED_LABEL` ("May 25, 2026") in src/page-views/NavyDesignatorsHub.tsx. */
    private const LAST_REVIEWED = '2026-05-25';

    /** Verbatim from the hub's `<ReferenceTrustFooter sourcePriority>` (NavyDesignatorsHub.tsx). */
    private const SOURCE_PRIORITY = 'We cite MyNavyHR / MILPERSMAN 1212-010 (Officer Designator Codes), OPNAV/BUPERS instructions, and navy.mil community pages first. Non-government sources are not used as primary evidence on this page.';

    /** Verbatim from the hub's `<ReferenceTrustFooter reviewCadence>` (NavyDesignatorsHub.tsx). */
    private const REVIEW_CADENCE = 'Designator codes, community membership, and accession pipelines are re-verified quarterly and any time NPC or BUPERS publishes a community-consolidation NAVADMIN.';

    public function __construct(
        private readonly RankRepositoryInterface $ranks,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of designator pages generated (hub + communities + details)
     */
    public function __invoke(): int
    {
        $designators = $this->ranks->designators();
        $count = 0;

        $this->upsert('designator-hub', PagePaths::root('designators'), [
            'page_type' => PageType::DesignatorHub,
            'slug' => 'navy-designators',
            'title' => 'U.S. Navy Officer Designators — Every 4-Digit Code Explained | NavyWeek.org',
            'h1' => 'NAVY OFFICER DESIGNATORS',
            'meta_description' => "Every U.S. Navy officer designator on one page — all {$designators->count()} four-digit codes across the Unrestricted Line, Restricted Line, and Staff Corps, with what each community does.",
            'og_image_path' => '/og/designators/hub.png',
            'trust_page_label' => 'Navy Officer Designators reference hub',
        ]);
        $count++;

        foreach (DesignatorCommunity::cases() as $community) {
            $inCommunity = $designators->filter(
                static fn (Rank $r): bool => $r->designator_community === $community
            );

            if ($inCommunity->isEmpty()) {
                continue;
            }

            $label = $community->label();
            $this->upsert("designator-community:{$community->value}", PagePaths::child('designators', $community->value), [
                'page_type' => PageType::DesignatorCommunityHub,
                'slug' => $community->value,
                'title' => "{$label} Officer Designators — U.S. Navy | NavyWeek.org",
                'h1' => mb_strtoupper($label),
                'meta_description' => "The {$inCommunity->count()} U.S. Navy {$label} officer designators, with the four-digit code, what each community does, and how officers are commissioned into it.",
                'og_image_path' => "/og/designators/{$community->value}.png",
                'trust_page_label' => "{$label} officer designators",
            ]);
            $count++;
        }

        foreach ($designators as $designator) {
            $page = $this->upsert("designator:{$designator->slug}", PagePaths::child('designators', $designator->slug), [
                'page_type' => PageType::Designator,
                'slug' => $designator->slug,
                'title' => $designator->meta_title ?? "{$designator->name} ({$designator->designator_code}) — U.S. Navy Officer Designator | NavyWeek.org",
                'h1' => mb_strtoupper("{$designator->name} ({$designator->designator_code})"),
                'meta_description' => $designator->meta_description,
                'og_image_path' => "/og/designators/{$designator->slug}.png",
                'trust_page_label' => "{$designator->name} designator page",
            ]);
            $page->pageable()->associate($designator)->save();
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(string $generationKey, string $path, array $attributes): Page
    {
        return $this->pages->upsertPillarPage($generationKey, $path, $attributes + [
            'last_reviewed' => self::LAST_REVIEWED,
            'sources_checked' => self::LAST_REVIEWED,
            'shows_reference_backlink' => true,
            'editorial_source_priority' => self::SOURCE_PRIORITY,
            'editorial_review_cadence' => self::REVIEW_CADENCE,
            'is_published' => true,
        ]);
    }
}
