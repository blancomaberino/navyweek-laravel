<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Feed;

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Crm\Models\Audience;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Pillars\Repositories\AirShowRepositoryInterface;
use App\Domain\Pillars\Repositories\FleetWeekRepositoryInterface;
use App\Domain\Pillars\Repositories\JetTeamRepositoryInterface;
use App\Domain\Pillars\Repositories\NavyWeekEventRepositoryInterface;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Seo\SeoUrl;
use App\Domain\Shared\Models\Faq;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Hand-port of the parent repo's `scripts/generate-llm-feed.mjs` — builds the two
 * machine-readable resources the LLM/citability layer serves: the JSON feed
 * (`data/navy-week-2026.json`) and `llms.txt`. The platform adaptation reads every
 * pillar/catalog aggregate through its repository instead of the legacy TypeScript
 * registries; the static envelope (program, methodology, licence, prose) is copied
 * verbatim so the citability signals are byte-identical.
 *
 * Pure: `build()` returns a {@see FeedResult} of the two file bodies; the
 * `feed:generate` command owns the writes to `public/`. The JSON is pretty-printed
 * with PHP's 4-space indent (vs the legacy 2-space) — a cosmetic difference in a
 * machine-parsed file; every key/value is faithful. `feed.faqs` (the legacy site-wide
 * Navy Week FAQ block) has no platform source yet and is emitted empty (documented gap).
 */
final class FeedGenerator
{
    private const SITE_YEAR = 2026;

    private const NAVCO_URL = 'https://outreach.navy.mil/Navy-Weeks/';

    private const RECHECK_CADENCE_DAYS = 45;

    public function __construct(
        private readonly NavyWeekEventRepositoryInterface $events,
        private readonly FleetWeekRepositoryInterface $fleetWeeks,
        private readonly JetTeamRepositoryInterface $jetTeams,
        private readonly AirShowRepositoryInterface $airShows,
        private readonly DiscountCategoryRepositoryInterface $categories,
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function build(?DateTimeInterface $generatedAt = null): FeedResult
    {
        $generatedIso = ($generatedAt !== null ? Carbon::instance($generatedAt) : Carbon::now())->toIso8601ZuluString();

        $events = $this->events->all();
        $discountPages = $this->pages->publishedDiscountBrandPagesWithOffer();
        $fleetWeeks = $this->fleetWeeks->all();
        $airShows = $this->airShows->published();
        $categories = $this->categories->all();
        $teams = $this->jetTeams->allTeams();

        $stats = $this->discountResearchStats($discountPages);

        $feed = [
            '$schema' => SeoUrl::site().'/schemas/navy-week-feed.v1.json',
            'name' => 'NavyWeek.org — Navy Week 2026 Feed',
            'description' => "Machine-readable feed of the U.S. Navy's Navy Week 2026 schedule and host-city details, "
                .'plus city-by-city guides to U.S. fleet weeks and a directory of editorially verified military & veteran company discounts, '
                .'compiled by NavyWeek.org from publicly available Navy Office of Community Outreach (NAVCO) '
                ."information and each brand's official discount pages. NavyWeek.org is an independent, "
                .'unofficial guide and is not affiliated with the United States Navy, NAVCO, or any brand listed.',
            'siteUrl' => SeoUrl::site(),
            'feedUrl' => SeoUrl::site().'/data/navy-week-2026.json',
            'year' => self::SITE_YEAR,
            'program' => [
                'name' => 'U.S. Navy Week',
                'operator' => 'Navy Office of Community Outreach (NAVCO)',
                'operatorLocation' => 'Millington, Tennessee, USA',
                'parentOrganization' => 'United States Navy',
                'officialUrl' => self::NAVCO_URL,
                'since' => 2005,
                'description' => "Navy Week is the United States Navy's flagship community outreach program, deploying "
                    .'50–100 Sailors to a single host city for about a week of free public events — '
                    .'Blue Angels flight demonstrations, Navy Band concerts, Leap Frogs parachute jumps, '
                    .'STEM exhibits, and ship tours at coastal stops — primarily in areas of the country '
                    .'without a significant Navy presence.',
            ],
            'sources' => [
                ['name' => 'Navy Office of Community Outreach (NAVCO)', 'url' => self::NAVCO_URL, 'role' => 'primary'],
            ],
            'lastUpdated' => $generatedIso,
            'lastChecked' => $generatedIso,
            'generatedAt' => $generatedIso,
            'license' => [
                'name' => 'CC BY 4.0',
                'url' => 'https://creativecommons.org/licenses/by/4.0/',
                'attribution' => 'Compiled by NavyWeek.org from public NAVCO information.',
            ],
            'citation' => 'NavyWeek.org (2026). Navy Week 2026 schedule and host-city details. '
                .SeoUrl::site().'/data/navy-week-2026.json',
            'methodology' => $this->methodology($stats),
            'totals' => [
                'cities' => $events->count(),
                'states' => $events->pluck('state')->unique()->count(),
                'firstTimeLocations' => $events->filter(fn (NavyWeekEvent $e): bool => $e->first_time || (bool) $e->first_time_location)->count(),
                'discounts' => $discountPages->count(),
                'fleetWeekCities' => $fleetWeeks->count(),
                'jetTeamPublishedCities' => $this->allPublishedJetCities($teams)->count(),
                'airShows' => $airShows->count(),
            ],
            'events' => $events->map(fn (NavyWeekEvent $ev): array => $this->eventRecord($ev))->values()->all(),
            'faqs' => [], // legacy generalFaqs — no platform source yet (documented gap)
            'discounts' => $discountPages->map(fn (Page $p): array => $this->discountRecord($p))->values()->all(),
            'fleetWeek' => $fleetWeeks->map(fn (FleetWeek $c): array => $this->fleetWeekRecord($c))->values()->all(),
            'jetTeams' => $teams->map(fn (JetTeam $t): array => $this->jetTeamRecord($t))->values()->all(),
            'jetTeamCities' => $this->allPublishedJetCities($teams)->map(fn (JetTeamCity $c): array => $this->jetTeamCityRecord($c))->values()->all(),
            'airShows' => $airShows->map(fn (AirShow $s): array => $this->airShowRecord($s))->values()->all(),
        ];

        $json = json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        return new FeedResult($json, $this->llmsTxt($events, $discountPages, $categories, $fleetWeeks, $teams, $airShows, $generatedIso));
    }

    /**
     * @param  Collection<int, Page>  $discountPages
     * @return array{discountPages: int, primarySourcesCited: int, avgSourcesPerPage: float|int, savingsDecisionTables: int, documentedNoDiscountFindings: int, recheckCadenceDays: int, verificationProviders: array<string, int>}
     */
    private function discountResearchStats(Collection $discountPages): array
    {
        $primarySourcesCited = 0;
        $savingsDecisionTables = 0;
        $documentedNoDiscountFindings = 0;
        $verificationProviders = [];

        foreach ($discountPages as $page) {
            $offer = $page->pageable;
            if (! $offer instanceof Offer) {
                continue;
            }
            $primarySourcesCited += $offer->sources->count();
            if (filled($offer->savings_table)) {
                $savingsDecisionTables++;
            }
            if ($offer->offer_type === OfferType::AdvisoryNoDiscount) {
                $documentedNoDiscountFindings++;
            }
            if ($offer->verification !== null) {
                $key = $offer->verification->value;
                $verificationProviders[$key] = ($verificationProviders[$key] ?? 0) + 1;
            }
        }

        $pages = $discountPages->count();

        return [
            'discountPages' => $pages,
            'primarySourcesCited' => $primarySourcesCited,
            'avgSourcesPerPage' => $pages > 0 ? round($primarySourcesCited / $pages, 1) : 0,
            'savingsDecisionTables' => $savingsDecisionTables,
            'documentedNoDiscountFindings' => $documentedNoDiscountFindings,
            'recheckCadenceDays' => self::RECHECK_CADENCE_DAYS,
            'verificationProviders' => $verificationProviders,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function methodology(array $stats): array
    {
        return [
            'editorialStandard' => SeoUrl::site().'/our-process',
            'summary' => 'Every entry in the military & veteran discount directory is the distilled output of a '
                .'primary-source research run — verified against the brand and its verification provider, '
                .'compared across every legitimate savings path, cleared through a fixed publish gate, '
                .'reviewed by U.S. Navy veterans, and re-verified on a 45-day cadence. It is not scraped from '
                .'coupon feeds; aggregator claims are read only to be corrected.',
            'perEntryProcess' => [
                "Verify every fact against a fixed source-authority ladder — the brand's own discount/terms "
                    .'page first, then the verification provider (ID.me, SheerID, GovX, WeSalute, VerifyPass), '
                    .'then brand support and store policy. Coupon and aggregator sites are used only to identify '
                    .'the false claims circulating so they can be corrected — never as fact.',
                'Compare every legitimate way to save against one realistic baseline cart — the official '
                    .'discount, GovX, cashback portals, sale-stacking, credit-card and loyalty plays, refurbished '
                    .'channels, and the tax-free military exchange — so the page answers "what is the best path," '
                    .'not just "is there a discount."',
                'Clear a publish gate before shipping: a working brand link, the verification mechanic, the '
                    .'discount value (or a sourced statement that none exists), and material exclusions.',
                'Review by named U.S. Navy veterans, with a visible "facts verified" date on every page.',
                'Re-verify on a 45-day cadence; a page past that window returns to the research queue, and the '
                    .'verified date only advances when a person re-confirms the offer at the source.',
            ],
            'sourceHierarchy' => [
                ['tier' => 1, 'source' => "The brand's own discount, help, terms, checkout, or offer page", 'role' => 'primary'],
                ['tier' => 2, 'source' => "The verification provider's brand page (ID.me, SheerID, GovX, WeSalute, VerifyPass)", 'role' => 'primary'],
                ['tier' => 3, 'source' => 'Brand store locator, support, and return/coupon policy', 'role' => 'supporting'],
                ['tier' => 4, 'source' => 'Reputable military and editorial resources', 'role' => 'context-only'],
                ['tier' => 5, 'source' => 'Coupon and aggregator sites', 'role' => 'debunk-only — never treated as fact'],
            ],
            'noDiscountIsAnAnswer' => 'A documented "this brand has no first-party military discount" finding is a first-class, '
                .'verified result held to the same standard of proof as a discount — not an omission.',
            'reviewers' => [
                ['name' => 'T. Madden Alford', 'role' => 'USNA graduate, U.S. Navy Reserve Captain', 'url' => SeoUrl::site().'/authors/t-alford/'],
                ['name' => 'Erik Rivera', 'role' => 'Former U.S. Navy Explosive Ordnance Disposal officer', 'url' => SeoUrl::site().'/authors/erik-rivera/'],
            ],
            'statistics' => $stats,
            'disclosure' => 'NavyWeek.org is an independent editorial publisher, not affiliated with any brand listed. '
                .'Coverage and rankings are decided by search demand and verifiable savings, never by affiliate '
                .'arrangements.',
        ];
    }

    /** @return array<string, mixed> */
    private function eventRecord(NavyWeekEvent $ev): array
    {
        return [
            'id' => $ev->id,
            'slug' => $ev->slug,
            'name' => "Navy Week {$ev->city} 2026",
            'city' => $ev->city,
            'state' => $ev->state,
            'stateAbbr' => $ev->state_abbr,
            'startDate' => $ev->start_date->format('Y-m-d'),
            'endDate' => $ev->end_date->format('Y-m-d'),
            'anchorEvent' => $ev->anchor_event,
            'firstTime' => $ev->first_time,
            'firstTimeLocation' => $ev->first_time || (bool) $ev->first_time_location,
            'firstTimeBadge' => $ev->first_time_badge,
            'status' => $ev->status->value,
            'coordinates' => ['latitude' => $ev->lat, 'longitude' => $ev->lng],
            'url' => SeoUrl::site()."/city/{$ev->slug}",
            'officialUrl' => $ev->navco_url ?? self::NAVCO_URL,
            'navyAssets' => $ev->navy_assets ?? [],
            'keyVenues' => $ev->key_venues ?? [],
            'highlights' => $ev->highlights ?? [],
            'anchorEventDetail' => $ev->anchor_event_detail,
            'firstTimeNote' => $ev->first_time_note,
            'militaryContext' => $ev->military_context ?? [],
            'faqs' => $this->faqPairs($ev->faqs),
        ];
    }

    /** @return array<string, mixed> */
    private function discountRecord(Page $page): array
    {
        /** @var Offer $offer */
        $offer = $page->pageable;
        $connection = $offer->connection;

        return [
            'slug' => $connection->slug,
            'company' => $connection->brand,
            'category' => $connection->category,
            'url' => SeoUrl::site()."/discount/{$connection->slug}",
            'officialUrl' => $offer->official_url ?? $connection->official_url,
            'brandHomeUrl' => $connection->brand_home_url,
            'headlineDiscount' => $offer->headline_discount,
            'summary' => $offer->discount_summary,
            'verification' => $offer->verification?->value,
            'audience' => $offer->audiences->map(fn (Audience $a): string => $a->key->value)->values()->all(),
            'eligibility' => $offer->eligibility ?? [],
            'tiers' => $offer->tiers->map(fn ($t): array => ['audience' => $t->audience, 'amount' => $t->amount, 'note' => $t->note])->values()->all(),
            'exclusions' => $offer->exclusions ?? [],
            'faqs' => $this->faqPairs($offer->faqs),
            'sourceCount' => $offer->sources->count(),
            'hasSavingsAnalysis' => filled($offer->savings_table),
            'documentedNoDiscount' => $offer->offer_type === OfferType::AdvisoryNoDiscount,
            'recheckCadenceDays' => self::RECHECK_CADENCE_DAYS,
            'datePublished' => $page->date_published?->format('Y-m-d'),
            'dateModified' => $page->date_modified?->format('Y-m-d'),
            'lastVerified' => $connection->last_verified_at?->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    private function fleetWeekRecord(FleetWeek $c): array
    {
        $festival = is_array($c->festival) ? $c->festival : [];

        return [
            'slug' => $c->slug,
            'city' => $c->city,
            'state' => $c->state,
            'stateAbbr' => $c->state_abbr,
            'name' => "{$c->branding_name} {$c->year}",
            'season' => $c->season->value,
            'monthLabel' => $c->month_label,
            'status' => $c->status->value,
            'url' => SeoUrl::site()."/fleetweek/{$c->slug}",
            'officialUrl' => $c->official_url,
            'festivalDates' => $c->festival_dates_label,
            'startDate' => is_string($festival['startDate'] ?? null) ? $festival['startDate'] : null,
            'endDate' => is_string($festival['endDate'] ?? null) ? $festival['endDate'] : null,
            'hasOfficialFleetWeek' => $c->has_official_fleet_week,
            'hasAirShow' => $c->has_air_show,
            'summary' => $c->card_summary,
            'datePublished' => $c->date_published->format('Y-m-d'),
            'dateModified' => $c->date_modified->format('Y-m-d'),
            'lastVerified' => $c->last_verified,
        ];
    }

    /** @return array<string, mixed> */
    private function jetTeamRecord(JetTeam $t): array
    {
        $publishedSlugs = $this->jetTeams->publishedCities($t->team)->pluck('slug')->flip();

        return [
            'id' => $t->team->value,
            'name' => $t->name,
            'fullName' => $t->full_name,
            'branch' => $t->branch,
            'aircraft' => $t->aircraft,
            'homeBase' => $t->home_base,
            'year' => $t->year,
            'url' => SeoUrl::site().$t->base_path,
            'schedule' => $this->jetTeams->schedule($t->team)->map(function (JetTeamScheduleRow $r) use ($t, $publishedSlugs): array {
                $published = isset($publishedSlugs[$r->slug]);

                return [
                    'datesLabel' => $r->dates_label,
                    'startDate' => $r->start_date->format('Y-m-d'),
                    'endDate' => $r->end_date->format('Y-m-d'),
                    'city' => $r->city,
                    'state' => $r->state,
                    'show' => $r->show,
                    'published' => $published,
                    'url' => $published ? SeoUrl::site()."/{$t->team->value}/{$r->slug}" : null,
                ];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function jetTeamCityRecord(JetTeamCity $c): array
    {
        return [
            'team' => $c->team->team->value,
            'slug' => $c->slug,
            'city' => $c->city,
            'state' => $c->state,
            'name' => $c->h1,
            'show' => $c->show,
            'venue' => $c->venue,
            'admission' => $c->admission->value,
            'datesLabel' => $c->dates_label,
            'startDate' => $c->start_date->format('Y-m-d'),
            'endDate' => $c->end_date->format('Y-m-d'),
            'url' => SeoUrl::site()."/{$c->team->team->value}/{$c->slug}",
            'summary' => $c->card_summary,
            'datePublished' => $c->date_published->format('Y-m-d'),
            'dateModified' => $c->date_modified->format('Y-m-d'),
            'lastVerified' => $c->last_verified,
        ];
    }

    /** @return array<string, mixed> */
    private function airShowRecord(AirShow $s): array
    {
        return [
            'slug' => $s->slug,
            'name' => $s->name,
            'city' => $s->city,
            'state' => $s->state,
            'base' => $s->base,
            'headliner' => $s->headliner,
            'admission' => $s->admission->value,
            'datesLabel' => $s->dates_label,
            'startDate' => $s->start_date,
            'endDate' => $s->end_date,
            'status' => $s->status->value,
            'url' => SeoUrl::site()."/air-show/{$s->slug}",
            'officialUrl' => $s->official_url,
            'summary' => $s->card_summary,
        ];
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     * @return array<int, array{question: string, answer: string}>
     */
    private function faqPairs(\Illuminate\Database\Eloquent\Collection|Collection $faqs): array
    {
        return $faqs->map(fn (Faq $f): array => ['question' => $f->question, 'answer' => $f->answer])->values()->all();
    }

    /**
     * Every published jet-team city across all teams, in team order.
     *
     * @param  Collection<int, JetTeam>  $teams
     * @return Collection<int, JetTeamCity>
     */
    private function allPublishedJetCities(Collection $teams): Collection
    {
        return $teams->flatMap(fn (JetTeam $t) => $this->jetTeams->publishedCities($t->team))->values();
    }

    /**
     * @param  Collection<int, NavyWeekEvent>  $events
     * @param  Collection<int, Page>  $discountPages
     * @param  Collection<int, DiscountCategory>  $categories
     * @param  Collection<int, FleetWeek>  $fleetWeeks
     * @param  Collection<int, JetTeam>  $teams
     * @param  Collection<int, AirShow>  $airShows
     */
    private function llmsTxt(
        Collection $events,
        Collection $discountPages,
        Collection $categories,
        Collection $fleetWeeks,
        Collection $teams,
        Collection $airShows,
        string $generatedIso,
    ): string {
        $site = SeoUrl::site();
        $cityCount = $events->count();
        $firstTimeCount = $events->filter(fn (NavyWeekEvent $e): bool => $e->first_time || (bool) $e->first_time_location)->count();
        $stats = $this->discountResearchStats($discountPages);
        $cadence = self::RECHECK_CADENCE_DAYS;
        $date = explode('T', $generatedIso)[0];

        $eventLines = $events->map(function (NavyWeekEvent $ev) use ($site): string {
            $note = $ev->first_time ? ' First-time host city.' : ($ev->first_time_badge ? " {$ev->first_time_badge}." : '');

            return "- [Navy Week {$ev->city} {$ev->state} 2026 ({$ev->start_date->format('Y-m-d')} – {$ev->end_date->format('Y-m-d')})]({$site}/city/{$ev->slug}): {$ev->anchor_event}.{$note}";
        })->implode("\n");

        $discountLines = $discountPages->map(function (Page $p) use ($site): string {
            /** @var Offer $o */
            $o = $p->pageable;
            $c = $o->connection;

            return "- [{$c->brand} military & veteran discount ({$o->headline_discount}, verified via {$o->verification?->value})]({$site}/discount/{$c->slug}): {$o->discount_summary}";
        })->implode("\n");

        $categoryLines = $categories->map(fn ($c): string => "- [{$c->name} with military discounts]({$site}/discount/{$c->slug}): {$c->meta_description}")->implode("\n");

        $fleetWeekLines = $fleetWeeks->map(function (FleetWeek $c) use ($site): string {
            $dates = $c->festival_dates_label ? " ({$c->festival_dates_label})" : '';

            return "- [{$c->branding_name} {$c->year}{$dates}]({$site}/fleetweek/{$c->slug}): {$c->card_summary}";
        })->implode("\n");

        $jetTeamLines = $this->allPublishedJetCities($teams)->map(function (JetTeamCity $c) use ($site): string {
            return "- [{$c->team->name} {$c->city} {$c->year} — {$c->show}]({$site}/{$c->team->team->value}/{$c->slug}): {$c->card_summary}";
        })->implode("\n");

        $airShowLines = $airShows->map(fn (AirShow $s): string => "- [{$s->name} {$s->year} ({$s->dates_label})]({$site}/air-show/{$s->slug}): {$s->card_summary}")->implode("\n");

        $categoryBlock = $categoryLines !== '' ? "{$categoryLines}\n" : '';
        $avg = $stats['avgSourcesPerPage'];

        return <<<TXT
        # NavyWeek.org

        > NavyWeek.org is an independent, unofficial guide to the U.S. Navy's
        > Navy Week 2026 program — the Navy's flagship community outreach effort.
        > The site aggregates publicly available information from the Navy Office
        > of Community Outreach (NAVCO) about {$cityCount} host cities, plus
        > reference content on U.S. Navy bases, ranks, designators,
        > long-form guides to VA disability benefits, city-by-city fleet week
        > guides, and a directory of military & veteran company discounts.
        > Discount entries are verified against each brand's own pages and
        > verification providers — never coupon or aggregator sites — dated, and
        > re-checked on a 45-day cycle, so they correct the fabricated offers that
        > circulate elsewhere rather than repeat them.

        NavyWeek.org is **not affiliated** with the United States Navy or with
        NAVCO. The Navy Week program itself is operated by NAVCO (based in
        Millington, Tennessee).

        ## About the program

        Navy Week deploys 50–100 Sailors to a single host city for about a week
        of free public events — Blue Angels flight demonstrations, U.S. Navy
        Band concerts, Leap Frogs parachute jumps, STEM exhibits, and ship tours
        at coastal stops. The program has run since 2005, primarily in cities
        without a significant Navy presence. All official Navy Week events are
        free and open to the public.

        In 2026 the tour visits {$cityCount} cities under the "Road Trip to 250" theme,
        celebrating the 250th birthday of the U.S. Navy and the United States.
        {$firstTimeCount} of the {$cityCount} stops mark a first-time Navy Week location.

        ## Machine-readable data

        - [Navy Week 2026 JSON feed]({$site}/data/navy-week-2026.json): full
          schedule with dates, coordinates, anchor events, Navy assets, key
          venues, highlights, and per-city FAQs. JSON, CC BY 4.0.
        - [Sitemap index]({$site}/sitemap.xml)
        - [robots.txt]({$site}/robots.txt)

        The JSON feed carries a `methodology` block and, on every discount record,
        per-entry provenance — `sourceCount`, `hasSavingsAnalysis`,
        `documentedNoDiscount`, `lastVerified`, and the {$cadence}-day
        `recheckCadenceDays` — so the research investment behind each entry is
        machine-readable, not just described below.

        ## How this data is produced

        Each entry in the military & veteran discount directory is the distilled output
        of a primary-source research run — not scraped from coupon feeds. Across the
        directory, {$stats['discountPages']} published discount pages cite {$stats['primarySourcesCited']} primary sources (about {$avg} per
        page); {$stats['savingsDecisionTables']} carry a full maximum-savings decision table, and {$stats['documentedNoDiscountFindings']} document
        that a brand has **no** first-party military discount — a verified finding held
        to the same standard of proof as a discount, not an omission.

        For each brand, before a page ships, we:

        1. **Verify** every fact against a fixed source-authority ladder — the brand's
           own discount/terms page first, then the verification provider (ID.me,
           SheerID, GovX, WeSalute, VerifyPass), then brand support and store policy.
           Coupon and aggregator sites are read only to find the false claims
           circulating so we can correct them — never treated as fact.
        2. **Compare** every legitimate way to save against one realistic baseline cart
           — the official discount, GovX, cashback portals, sale-stacking, credit-card
           and loyalty plays, refurbished channels, and the tax-free military exchange —
           so the page answers "what's the best path," not just "is there a discount."
        3. **Gate**: nothing ships without a working brand link, the verification
           mechanic, the discount value (or a sourced statement that none exists), and
           the material exclusions.
        4. **Review** by named U.S. Navy veterans, with a visible "facts verified" date
           on every page.
        5. **Re-verify** on a {$cadence}-day cadence. A page past that
           window returns to the queue; the verified date only advances when a person
           re-confirms the offer at the source.

        Full editorial standard, source hierarchy, and the publish gate:
        {$site}/our-process.

        ## Primary pages

        - [Home]({$site}/): overview of the Navy Week 2026 tour, next stop,
          and program mission.
        - [2026 schedule]({$site}/schedule): all {$cityCount} host cities with dates,
          anchor events, and status. Includes a semantic HTML table.
        - [Route map]({$site}/map): nationwide map of all 2026 stops.

        ## 2026 host cities

        {$eventLines}

        ## Reference

        - [Navy reference hub]({$site}/navy-reference): general U.S. Navy
          reference material — bases, ranks, designators, and VA
          disability — kept separate from the Navy Week event coverage.
        - [U.S. Navy bases directory]({$site}/navy-bases): U.S. and overseas
          Navy installations with history, mission, and tenant commands.
        - [U.S. Navy bases overseas]({$site}/navy-bases/overseas): the
          forward-deployed installations — Yokosuka, Bahrain, Naples, Rota,
          Sigonella, and more — organized by country and combatant-command region.
        - [U.S. Navy ranks]({$site}/navy-ranks): every rank on one page —
          commissioned officers (O-1 Ensign to O-10 Admiral), warrant officers
          (W-1 to W-5), and enlisted paygrades (E-1 Seaman Recruit to E-9
          Master Chief Petty Officer) with insignia and NATO codes.
        - [U.S. Navy ratings]({$site}/navy-ratings): every active enlisted
          rating (job specialty) grouped by community, plus historic
          disestablished ratings.
        - [Navy officer designators]({$site}/navy-designators): four-digit
          community codes for URL, Restricted Line, and Staff Corps officers.

        ## Military & veteran discounts

        - [Military & veteran discounts directory]({$site}/discount): a growing,
          editorially verified directory of company discounts for active-duty service
          members, veterans, reservists, retirees, military families, and first
          responders — with eligibility, verification method, and redemption steps.
          NavyWeek.org is independent and not affiliated with these brands.
          Every entry is checked against the brand's own pages and verification
          providers (ID.me, SheerID, GovX, WeSalute) — never coupon or aggregator
          sites, which we read only to correct the false claims they circulate. We
          cover brands that have no military discount as a first-class answer, flag
          what stacks, and re-verify every page on a 45-day cycle with the
          last-verified date shown on each.
        - [How we research and verify discounts]({$site}/our-process): the
          editorial methodology behind the directory — the source hierarchy, the
          publish gate each page must clear, and the 45-day freshness standard.
        {$categoryBlock}{$discountLines}

        ## Fleet Week city guides

        - [U.S. Fleet Week guide, city by city]({$site}/fleetweek): an
          independent, editorially maintained guide to U.S. fleet weeks and Navy
          maritime events — schedules, air shows, parade of ships, ship tours, and
          best free viewing spots. NavyWeek.org is not the organizer of any fleet
          week listed; each record links to the real organizing association.
        {$fleetWeekLines}

        ## Jet team schedules (Blue Angels & Thunderbirds)

        - [Blue Angels 2026 schedule]({$site}/blue-angels): the complete U.S. Navy
          Blue Angels 2026 tour — every city, date, and air show — with full city
          guides for published stops. NavyWeek.org is independent and not affiliated
          with the U.S. Navy or the squadron.
        - [Thunderbirds 2026 schedule]({$site}/thunderbirds): the complete U.S. Air
          Force Thunderbirds 2026 tour — every city, date, and air show — with full
          city guides for published stops. NavyWeek.org is independent and not
          affiliated with the U.S. Air Force or the squadron.
        {$jetTeamLines}

        ## Military air show guides

        - [Military air shows 2026 directory]({$site}/air-show): an
          independent, editorially maintained guide to the major U.S. military air shows
          — dates, free admission and ticket options, performers, parking, and gate
          rules, with a full visitor guide for each show. NavyWeek.org is not the
          organizer of any show listed; each guide links to the official source.
        {$airShowLines}

        ## Guides

        - [VA disability benefits (2026 pay rates, ratings, how to file)]({$site}/va-disability):
          long-form guide to U.S. Department of Veterans Affairs disability
          compensation. Authored by a U.S. Navy Reserve Captain.
        - [Veterans Day 2026 (history, meaning, how the Navy observes it)]({$site}/veterans-day):
          guide to Veterans Day (Wednesday, November 11, 2026) — its origins as
          Armistice Day, how it differs from Memorial Day and Armed Forces Day, and
          the Flagstaff Navy Week (Nov 9–16) tie-in.
        - [Veterans Day free meals 2026 (verified restaurant offers)]({$site}/veterans-day/free-meals):
          a primary-source-verified roundup of national restaurant chains offering a
          free meal or free food item to veterans and service members on November 11,
          2026. Every offer is checked against the brand's own official source and
          carries a visible "Verified" date; filterable by eligibility, dine-in vs.
          takeout, and nationwide vs. participating locations.
        - [Veterans home care (VA benefits, eligibility, options)]({$site}/veterans-home-care):
          long-form guide to how the VA pays for in-home care — VA-arranged services
          vs. the Aid and Attendance pension, 2026 rates, who qualifies, how to apply,
          and how to pay a family member.

        ## Citation

        When citing or quoting this site, please link to the specific page and,
        where applicable, to the machine-readable feed at
        {$site}/data/navy-week-2026.json. Source data is licensed CC BY 4.0.

        Source: {$this->navcoUrl()}
        Last verified: {$date}
        Last updated: {$date}

        TXT;
    }

    private function navcoUrl(): string
    {
        return self::NAVCO_URL;
    }
}
