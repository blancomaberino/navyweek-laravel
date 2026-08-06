<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Fills the per-page editorial-policy copy the shared trust footer renders —
 * `trust_page_label`, `editorial_source_priority` and `editorial_review_cadence`.
 *
 * The live site does NOT use one generic paragraph: every page view composes its
 * own wording from the record it renders (see the `<ReferenceTrustFooter …>` call
 * in each `src/page-views/*.tsx`), naming the actual organizer, team, brand or
 * dataset. Seeding one house string instead left the box a line short of the live
 * page on every family that carries it. The strings below are copied verbatim from
 * those call sites; the only substitutions are the record fields the legacy
 * interpolates.
 *
 * Idempotent and non-destructive: an editor-supplied value already in a column is
 * left alone — this only fills what is still blank, unless `--force`.
 */
final class BackfillEditorialPolicyCommand extends Command
{
    protected $signature = 'backfill:editorial-policy {--force : Overwrite existing values}';

    protected $description = 'Compose the per-record editorial-policy copy the legacy page views render';

    /**
     * Pages that key off `slug` rather than a pageable — the standalone hubs and
     * guides, whose legacy wording is a fixed string.
     *
     * @var array<string, array{label: string, source: string, cadence: string}>
     */
    private const BY_SLUG = [
        'navy-reference' => [
            'label' => 'Navy Reference hub',
            'source' => 'We cite navy.mil, MyNavyHR / MILPERSMAN, DFAS pay tables, and CNIC first. Reference totals are derived from datasets in src/lib/bases and src/lib/ranks, each of which is sourced from official .mil pages.',
            'cadence' => 'Catalogue totals (bases, ranks, designators) are re-verified whenever the underlying datasets are updated and at every quarterly editorial pass.',
        ],
        'best-credit-cards-for-military' => [
            'label' => 'Best credit cards for military',
            'source' => "Legal claims cite the SCRA and MLA statutes, the CFPB, and the DOJ; every issuer fee-waiver claim cites that issuer's own military-benefits page, with fees date-stamped. Community-sourced claims are flagged and never stated as fact.",
            'cadence' => 'Card fees, offers, and issuer waiver policies change frequently, so this page is re-verified quarterly (next review: October 2026) and immediately after any major issuer fee change.',
        ],
        'discount' => [
            'label' => 'Military discounts directory',
            'source' => "For each brand we cite the company's official discount page and its identity-verification provider (such as ID.me or GovX) first. Discount amounts, eligibility, and exclusions are quoted from those sources and dated on each guide.",
            'cadence' => 'Because brands can change discount terms at any time, each guide is re-verified against the official page on a recurring basis and whenever a reader reports a change.',
        ],
    ];

    /**
     * Pages that key off `page_type` alone — the reference hubs, whose legacy
     * wording is a fixed string.
     *
     * @var array<string, array{label: string, source: string, cadence: string}>
     */
    private const BY_TYPE = [
        PageType::Rank->value => [
            'label' => 'Navy Ranks reference hub',
            'source' => 'We cite navy.mil insignia plates, MyNavyHR / MILPERSMAN, and DFAS basic pay tables first; the U.S. Code (Title 10) and eCFR where statutes apply. Non-government sources are not used as primary evidence on this page.',
            'cadence' => 'Insignia, NATO codes, and rank structure are re-verified quarterly and at every page update.',
        ],
        PageType::Rating->value => [
            'label' => 'Navy Ratings reference hub',
            'source' => 'We cite navy.mil, MyNavyHR enlisted community pages, and Navy COOL rating cards first. Non-government sources are not used as primary evidence on this page.',
            'cadence' => 'The active-rating roster and community groupings are re-verified quarterly and at every page update; historic ratings are checked against Navy disestablishment notices.',
        ],
        PageType::DesignatorHub->value => [
            'label' => 'Navy Officer Designators reference hub',
            'source' => 'We cite MyNavyHR / MILPERSMAN 1212-010 (Officer Designator Codes), OPNAV/BUPERS instructions, and navy.mil community pages first. Non-government sources are not used as primary evidence on this page.',
            'cadence' => 'Designator codes, community membership, and accession pipelines are re-verified quarterly and any time NPC or BUPERS publishes a community-consolidation NAVADMIN.',
        ],
        PageType::BaseHub->value => [
            'label' => 'Navy Bases reference hub',
            'source' => 'We cite Commander, Navy Installations Command (CNIC) regional pages, individual installation public-affairs pages on navy.mil, and DoD installation listings first. Historical claims are sourced from the Naval History and Heritage Command. Non-government sources are not used as primary evidence on this page.',
            'cadence' => 'Base totals, state counts, and installation-type counts are re-verified whenever the underlying dataset is updated. Largest-base claims, CO/CNIC structure, and visitor-access framing are re-verified quarterly.',
        ],
        PageType::BaseOverseasHub->value => [
            'label' => 'Navy Bases Overseas reference hub',
            'source' => 'We cite CNIC regional pages, individual installation public-affairs pages on navy.mil, State Department SOFA / treaty pages, and DoD overseas installation listings first. Host-nation context is sourced from official host-nation government pages where relevant. Non-government sources are not used as primary evidence on this page.',
            'cadence' => 'Overseas base totals, host-nation counts, and combatant-command alignment are re-verified whenever the underlying dataset is updated. SOFA references, command-sponsorship framing, and largest-overseas-base claims are re-verified quarterly.',
        ],
    ];

    public function handle(PageRepositoryInterface $pages): int
    {
        $force = (bool) $this->option('force');
        $filled = 0;
        $ymyl = $this->ymylPolicy();

        foreach ($pages->allPublishedIndexable() as $page) {
            $copy = $this->copyFor($page);
            $perPage = $ymyl[$page->slug] ?? [];
            if ($copy === null && $perPage === []) {
                continue;
            }

            $updates = $copy === null ? [] : array_filter([
                'trust_page_label' => $this->valueFor($page, 'trust_page_label', $force, $copy['label']),
                'editorial_source_priority' => $this->valueFor($page, 'editorial_source_priority', $force, $copy['source']),
                'editorial_review_cadence' => $this->valueFor($page, 'editorial_review_cadence', $force, $copy['cadence']),
            ], static fn (?string $value): bool => $value !== null);

            // The two VA guides ship their OWN EditorialPolicyBox — all six
            // bullets differ, and the Reviewer one carries the "not a
            // VA-accredited representative" disclaimer, which is load-bearing on
            // a benefits page. Every other family uses the shared component, so
            // the partial's fallback wording is already correct for them.
            foreach ($perPage as $column => $text) {
                $value = $this->valueFor($page, $column, $force, $text);
                if ($value !== null) {
                    $updates[$column] = $value;
                }
            }

            if ($updates !== []) {
                $page->forceFill($updates)->save();
                $filled++;
            }
        }

        $this->info("Editorial policy copy written to {$filled} pages.");

        return self::SUCCESS;
    }

    /**
     * The per-page policy bullets for the guides that write their own, keyed
     * page slug => [pages column => text]. Extracted verbatim from each view's
     * `EditorialPolicyBox()` / `CorrectionsBox()` into a committed seed artifact.
     *
     * @return array<string, array<string, string>>
     */
    private function ymylPolicy(): array
    {
        $path = database_path('seed-data/ymyl-editorial-policy.json');
        if (! is_file($path)) {
            return [];
        }

        /** @var array{editorialPolicy?: array<string, array<string, string>>} $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $columns = [
            'source_priority' => 'editorial_source_priority',
            'review_cadence' => 'editorial_review_cadence',
            'independence' => 'editorial_independence',
            'reviewer' => 'editorial_reviewer_note',
            'corrections' => 'editorial_corrections',
            'not_advice' => 'editorial_not_advice',
            'corrections_note' => 'corrections_note',
        ];

        $out = [];
        foreach ($data['editorialPolicy'] ?? [] as $slug => $bullets) {
            foreach ($bullets as $key => $text) {
                if (isset($columns[$key]) && $text !== '') {
                    $out[$slug][$columns[$key]] = $text;
                }
            }
        }

        return $out;
    }

    /**
     * The legacy wording for this page, or null when its page view renders no
     * editorial-policy footer at all.
     *
     * @return array{label: string, source: string, cadence: string}|null
     */
    private function copyFor(Page $page): ?array
    {
        if (isset(self::BY_SLUG[$page->slug])) {
            return self::BY_SLUG[$page->slug];
        }

        if (isset(self::BY_TYPE[$page->page_type->value])) {
            return self::BY_TYPE[$page->page_type->value];
        }

        $pageable = $page->pageable;

        return match (true) {
            $pageable instanceof FleetWeek => $this->fleetWeekCopy($pageable),
            $pageable instanceof AirShow => $this->airShowCopy($pageable),
            $pageable instanceof AirShowHubMeta => $this->airShowHubCopy($pageable),
            $pageable instanceof JetTeamCity => $this->jetTeamCityCopy($pageable),
            $pageable instanceof JetTeam => $this->jetTeamHubCopy($pageable),
            $pageable instanceof DiscountCategory => $this->discountCategoryCopy($pageable),
            $pageable instanceof Offer => $this->discountBrandCopy($pageable),
            default => $this->hubCopyFor($page),
        };
    }

    /**
     * The two pillar hubs that own no pageable but still carry the footer.
     *
     * @return array{label: string, source: string, cadence: string}|null
     */
    private function hubCopyFor(Page $page): ?array
    {
        return match ($page->slug) {
            'fleetweek' => [
                'label' => 'Fleet Week directory',
                'source' => "For each city we cite the official fleet week organizer's site and primary news coverage first. Dates, air-show schedules, and ship-tour details are quoted from those sources and dated on each guide.",
                'cadence' => 'Because organizers can change dates and schedules at any time, each guide is re-verified against the official site on a recurring basis and whenever a reader reports a change.',
            ],
            default => null,
        };
    }

    /**
     * Ported from FleetWeekDetail.tsx — the wording branches on whether the city
     * has an official organizer at all.
     *
     * @return array{label: string, source: string, cadence: string}
     */
    private function fleetWeekCopy(FleetWeek $week): array
    {
        $hasOrganizer = (bool) $week->has_official_fleet_week || filled($week->festival);
        $siteLabel = filled($week->official_site_label) ? " ({$week->official_site_label})" : '';

        return [
            'label' => "{$week->city} Fleet Week {$week->year} guide",
            'source' => $hasOrganizer
                ? "We cite the official {$week->branding_name} organizer site{$siteLabel} and primary news coverage first. Dates, schedules, and event details are quoted from those sources and confirmed on the \"Last verified\" date above."
                : "Because there is no official {$week->city} fleet week organizer, we cite primary and authoritative sources — official U.S. Navy and Naval History and Heritage Command pages, museum and municipal sites, and reputable news coverage — for everything on this page, confirmed on the \"Last verified\" date above.",
            'cadence' => $hasOrganizer
                ? 'Because the organizer can change dates, performers, and schedules at any time, this guide is re-verified against the official site on a recurring basis and whenever a reader reports a change.'
                : 'This is a background and history guide rather than an event listing, so we re-check the cited sources on a recurring basis and whenever a reader reports a change.',
        ];
    }

    /**
     * Ported from AirShowHub.tsx — the directory hub names no single show, so its
     * wording is generic over "each show".
     *
     * @return array{label: string, source: string, cadence: string}
     */
    private function airShowHubCopy(AirShowHubMeta $hub): array
    {
        return [
            'label' => "U.S. military air shows {$hub->year} guide",
            'source' => "We cite each show's official announcements and the participating military teams' schedules first. Dates, performers, and locations are quoted from those sources and dated above.",
            'cadence' => 'Because the military and show organizers can change dates at any time, this guide is re-verified against the official sources on a recurring basis and whenever a reader reports a change.',
        ];
    }

    /**
     * Ported from AirShowDetail.tsx.
     *
     * @return array{label: string, source: string, cadence: string}
     */
    private function airShowCopy(AirShow $show): array
    {
        return [
            'label' => "{$show->name} {$show->year} guide",
            'source' => "We cite the official {$show->name} announcements and the participating military teams' schedules first. Dates, performers, and admission details are quoted from those sources and confirmed on the \"Last verified\" date above.",
            'cadence' => 'Because the organizer and the military can change dates and performers at any time, this guide is re-verified against the official sources on a recurring basis and whenever a reader reports a change.',
        ];
    }

    /**
     * Ported from JetTeamDetail.tsx.
     *
     * @return array{label: string, source: string, cadence: string}
     */
    private function jetTeamCityCopy(JetTeamCity $stop): array
    {
        $team = $stop->team;

        return [
            'label' => "{$team->name} {$stop->city} {$stop->year} guide",
            'source' => "We cite the official {$stop->show} announcements and the {$team->branch} {$team->name} schedule first. Dates, show times, and locations are quoted from those sources and confirmed on the \"Last verified\" date above.",
            'cadence' => 'Because the organizer and the military can change dates and schedules at any time, this guide is re-verified against the official sources on a recurring basis and whenever a reader reports a change.',
        ];
    }

    /**
     * Ported from JetTeamHub.tsx.
     *
     * @return array{label: string, source: string, cadence: string}
     */
    private function jetTeamHubCopy(JetTeam $team): array
    {
        return [
            'label' => "{$team->name} {$team->year} schedule",
            'source' => "We cite the official {$team->branch} {$team->name} schedule and each host show's announcements first. Dates, show names, and locations are quoted from those sources and dated above.",
            'cadence' => 'Because the military and show organizers can change dates at any time, this schedule is re-verified against the official sources on a recurring basis and whenever a reader reports a change.',
        ];
    }

    /**
     * Ported from DiscountCategory.tsx.
     *
     * @return array{label: string, source: string, cadence: string}
     */
    private function discountCategoryCopy(DiscountCategory $category): array
    {
        return [
            'label' => "{$category->name} discounts",
            'source' => "For each brand we cite the company's official discount page and its identity-verification provider (such as ID.me or in-store ID) first. Discount amounts, eligibility, and exclusions are quoted from those sources and dated on each brand's guide.",
            'cadence' => 'Because brands can change discount terms at any time, each guide is re-verified against the official page on a recurring basis and whenever a reader reports a change.',
        ];
    }

    /**
     * Ported from DiscountDetail.tsx — the brand's own verification method is
     * named in the sentence, so it reads differently on every guide.
     *
     * Null when the offer names no verification provider — the sentence quotes it,
     * and inventing one would assert a fact the record doesn't carry.
     *
     * @return array{label: string, source: string, cadence: string}|null
     */
    private function discountBrandCopy(Offer $offer): ?array
    {
        // The enum's backing values are the legacy registry's `verification`
        // strings verbatim ("ID.me", "GovX", "In-store ID", …), so the sentence
        // reads exactly as it does on the live guide.
        $provider = $offer->verification;
        if ($provider === null) {
            return null;
        }

        $brand = $offer->connection->brand;
        $verification = $provider->value;

        // The legacy guide renders `d.sourcePriorityNote ?? {generic default}` — a
        // brand that documents its own sourcing (roughly half of them do) states it
        // in its own words, so the record's note wins over the house sentence.
        $note = $offer->source_priority_note;

        return [
            'label' => "{$brand} military discount page",
            'source' => filled($note)
                ? $note
                : "We cite {$brand}'s official discount page and the identity-verification provider ({$verification}) first. Discount amounts, eligibility, and exclusions are quoted from those sources and confirmed on the \"Last verified\" date above.",
            'cadence' => "Because {$brand} can change these terms at any time, the offer is re-verified against the official page on a recurring basis and whenever a reader reports a change.",
        ];
    }

    /**
     * The new value for a column, or null to leave it as it is.
     */
    private function valueFor(Page $page, string $column, bool $force, string $candidate): ?string
    {
        if (! $force && filled($page->{$column})) {
            return null;
        }

        return $page->{$column} === $candidate ? null : $candidate;
    }
}
