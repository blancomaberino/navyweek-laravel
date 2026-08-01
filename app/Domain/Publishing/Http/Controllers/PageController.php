<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Controllers;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\LocalDiscountRepositoryInterface;
use App\Domain\Crm\Models\Connection;
use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Pillars\Models\Rank;
use App\Domain\Pillars\Repositories\AirShowRepositoryInterface;
use App\Domain\Pillars\Repositories\FleetWeekRepositoryInterface;
use App\Domain\Pillars\Repositories\JetTeamRepositoryInterface;
use App\Domain\Pillars\Repositories\RankRepositoryInterface;
use App\Domain\Pillars\Seo\AirShowPageSchema;
use App\Domain\Pillars\Seo\BasePageSchema;
use App\Domain\Pillars\Seo\FleetWeekPageSchema;
use App\Domain\Pillars\Seo\JetTeamPageSchema;
use App\Domain\Pillars\Seo\NavyWeekCitySchema;
use App\Domain\Pillars\Seo\RankListSchema;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Seo\ContentPageSchema;
use App\Domain\Publishing\Seo\DiscountCategorySchema;
use App\Domain\Publishing\Seo\DiscountGuideSchema;
use App\Domain\Publishing\Seo\DiscountIndexSchema;
use App\Domain\Publishing\Seo\LocalDiscountHubSchema;
use App\Domain\Publishing\Seo\LocalDiscountSchema;
use App\Domain\Publishing\Seo\SeoHead;
use App\Domain\Publishing\Seo\SeoUrl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single catch-all page renderer, keyed on `pages.url_path`. By the time a request
 * reaches here CanonicalUrlMiddleware has already resolved every redirect, so an
 * unknown path is a genuine 404 (the middleware sends stray legacy URLs to "/").
 *
 * The base layout ports the legacy `<head>` furniture 1:1 and `SeoHead` serializes
 * the per-page SEO block. The body is dispatched by `page_type`: a discount-brand
 * page renders the full guide from its primary Offer; every other type falls back
 * to the minimal shell until its own page-family view lands. Response caching is a
 * later slice.
 */
final class PageController
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly DiscountCategoryRepositoryInterface $categories,
        private readonly RankRepositoryInterface $ranks,
        private readonly AirShowRepositoryInterface $airShows,
        private readonly FleetWeekRepositoryInterface $fleetWeeks,
        private readonly JetTeamRepositoryInterface $jetTeams,
        private readonly LocalDiscountRepositoryInterface $localDiscounts,
    ) {}

    public function show(Request $request): Response
    {
        // Look up by the exact path the middleware already canonicalized and
        // validated — re-normalizing here (lowercasing/slash-collapsing) would use
        // a different key than the middleware's existence check and could serve a
        // page for a non-canonical URL the middleware meant to 301.
        $page = $this->pages->findPublishedByPath($request->getPathInfo());

        if ($page === null) {
            abort(404);
        }

        // Dispatch to the page-family renderer; a page whose pageable isn't the
        // aggregate its type expects falls through to the minimal shell (null → shell).
        return $this->renderBody($page) ?? $this->renderShell($page);
    }

    /**
     * Render the body for a live page keyed on its `page_type`, or null to fall back
     * to the shell. Each new page family adds one match arm here instead of growing
     * `show()` — the `instanceof` guard keeps a type-mismatched pageable on the shell.
     */
    private function renderBody(Page $page): ?Response
    {
        $pageable = $page->pageable;

        return match ($page->page_type) {
            PageType::DiscountBrand => $pageable instanceof Offer
                ? $this->renderDiscountGuide($page, $pageable)
                : null,
            PageType::DiscountCategoryHub => $pageable instanceof DiscountCategory
                ? $this->renderDiscountCategory($page, $pageable)
                : null,
            PageType::Base => $pageable instanceof Base
                ? $this->renderBase($page, $pageable)
                : null,
            // The two consolidated reference lists own no pageable — they read the
            // whole rank pillar at render.
            PageType::Rank => $this->renderRankList($page),
            PageType::Rating => $this->renderRatingList($page),
            // Air-show detail and hub share the type, split by pageable class.
            PageType::AirShow => match (true) {
                $pageable instanceof AirShow => $this->renderAirShow($page, $pageable),
                $pageable instanceof AirShowHubMeta => $this->renderAirShowHub($page, $pageable),
                default => null,
            },
            // Fleet-week city (pageable = FleetWeek) vs the hub (no pageable).
            PageType::FleetWeek => $pageable instanceof FleetWeek
                ? $this->renderFleetWeek($page, $pageable)
                : $this->renderFleetWeekHub($page),
            PageType::NavyWeekCity => $pageable instanceof NavyWeekEvent
                ? $this->renderNavyWeekCity($page, $pageable)
                : null,
            PageType::JetTeam => $pageable instanceof JetTeam
                ? $this->renderJetTeamHub($page, $pageable)
                : null,
            PageType::JetTeamCity => $pageable instanceof JetTeamCity
                ? $this->renderJetTeamCity($page, $pageable)
                : null,
            // Local-business detail (pageable = LocalDiscount) vs the /discounts/ rollup
            // hubs (null pageable — root / per-state / per-city, split by url depth).
            PageType::LocalDiscount => $pageable instanceof LocalDiscount
                ? $this->renderLocalDiscount($page, $pageable)
                : $this->renderLocalDiscountHub($page),
            // DB-driven content page: Article + author Person + FAQPage.
            PageType::VeteransDayHub => $this->renderVeteransDay($page),
            // Static hubs/content pages are dispatched by slug; unknown slugs → shell.
            // (Every PageType now has an arm — no default; a new case is caught by PHPStan.)
            PageType::Static => $this->renderStatic($page),
        };
    }

    /**
     * Static pages, dispatched by slug. The `/discount/` directory is the first; other
     * static/content pages (privacy, terms, …) add an arm here. An unrecognized static
     * slug returns null → the minimal shell.
     */
    private function renderStatic(Page $page): ?Response
    {
        return match ($page->slug) {
            'discount' => $this->renderDiscountIndex($page),
            'privacy' => $this->renderContentPage($page, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Privacy Policy', 'url' => '/privacy/'],
            ]),
            'terms' => $this->renderContentPage($page, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Terms of Use', 'url' => '/terms/'],
            ]),
            'contact' => $this->renderContentPage($page, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Contact', 'url' => '/contact/'],
            ]),
            'va-disability' => $this->renderYmylGuide(
                $page,
                'VA Disability Benefits Guide (2026 Pay Rates, Ratings, How to File)',
                'VA Disability Benefits Guide (2026 Pay Rates, Ratings, How to File)',
                'An independent, plain-language guide to VA disability compensation — 2026 pay rates, how ratings work, how to file, and where to get free accredited help.',
            ),
            'veterans-home-care' => $this->renderYmylGuide(
                $page,
                "Veterans Home Care: A Family's Guide to VA Benefits, Eligibility, and Options",
                "Veterans Home Care: A Family's Guide to VA Benefits, Eligibility, and Options",
                'An independent guide to veterans home care — VA-arranged services vs. the Aid and Attendance pension, 2026 rates, who qualifies, and how to apply.',
            ),
            default => null,
        };
    }

    /**
     * A YMYL guide content page (`/va-disability/`, `/veterans-home-care/`): the graph is
     * Article + author Person + reviewer Person + WebPage, and deliberately NO FAQPage
     * (`ContentPageSchema` with `emitWebPage`). Body is the editor-managed `body_blocks`.
     */
    private function renderYmylGuide(Page $page, string $heading, string $articleHeadline, string $description): Response
    {
        $page->load(['author', 'reviewer']);
        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Navy Reference', 'url' => '/navy-reference/'],
            ['name' => $heading, 'url' => $page->url_path],
        ];

        $seo = SeoHead::forPage($page, ContentPageSchema::build(
            $page,
            $crumbs,
            headline: $articleHeadline,
            articleDescription: $description,
            emitWebPage: true,
        ));

        return response()->view('pages.content', [
            'page' => $page,
            'crumbs' => $crumbs,
            'heading' => $heading,
            'blocks' => $page->body_blocks ?? [],
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A DB-driven content page: renders the CMS-editable `body_blocks` under a
     * breadcrumb. The JSON-LD is Organization (prepended by SeoHead) + BreadcrumbList
     * (`ContentPageSchema`); richer content-page graphs add their nodes in later slices.
     *
     * @param  list<array{name: string, url: string}>  $crumbs
     */
    private function renderContentPage(Page $page, array $crumbs): Response
    {
        $seo = SeoHead::forPage($page, ContentPageSchema::build($page, $crumbs));

        return response()->view('pages.content', [
            'page' => $page,
            'crumbs' => $crumbs,
            'heading' => (string) $page->title,
            'blocks' => $page->body_blocks ?? [],
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The `/veterans-day/` reference article — a DB-driven content page whose graph is
     * Article + author Person + FAQPage (`ContentPageSchema` with `emitFaqPage`). The
     * body + FAQs are editor-managed; the byline drives the author Person.
     */
    private function renderVeteransDay(Page $page): Response
    {
        $page->load(['author', 'reviewer', 'faqs']);
        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Navy Reference', 'url' => '/navy-reference/'],
            ['name' => 'Veterans Day', 'url' => '/veterans-day/'],
        ];

        $seo = SeoHead::forPage($page, ContentPageSchema::build(
            $page,
            $crumbs,
            headline: 'Veterans Day 2026: History, Meaning, and How the Navy Observes It',
            articleDescription: 'An independent guide to Veterans Day 2026 (Wednesday, November 11): its history from Armistice Day, how it differs from Memorial Day and Armed Forces Day, how the U.S. Navy observes it, and ways to honor Navy veterans — including the Flagstaff Navy Week tie-in.',
            emitFaqPage: true,
        ));

        return response()->view('pages.content', [
            'page' => $page,
            'crumbs' => $crumbs,
            'heading' => 'Veterans Day 2026: History, Meaning & How the Navy Observes It',
            'blocks' => $page->body_blocks ?? [],
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The `/discount/` directory landing page: the JSON-LD ItemList over every
     * published discount-brand page + the hub FAQs (seeded on the page).
     */
    private function renderDiscountIndex(Page $page): Response
    {
        $page->load('faqs');
        $brandPages = $this->pages->allPublishedDiscountBrandPages();

        $seo = SeoHead::forPage($page, DiscountIndexSchema::build($page, $brandPages, $page->faqs));

        return response()->view('pages.discount-index', [
            'page' => $page,
            'brandPages' => $brandPages,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /** The minimal shell for a page type that has no dedicated view yet. */
    private function renderShell(Page $page): Response
    {
        $seo = SeoHead::forPage($page);

        return response()->view('pages.show', [
            'page' => $page,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    private function renderDiscountGuide(Page $page, Offer $offer): Response
    {
        // Every child relation already orders by sort_order in its definition.
        $offer->load(['connection', 'tiers', 'redemptionSteps', 'faqs', 'sources', 'audiences']);
        // The byline persons for the Article/WebPage JSON-LD.
        $page->load(['author', 'reviewer']);

        $seo = SeoHead::forPage($page, DiscountGuideSchema::build($page, $offer));

        return response()->view('pages.discount', [
            'page' => $page,
            'offer' => $offer,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A local-business discount detail page (`/discounts/{state}/{city}/{business}/`).
     * The graph is the discount-guide E-E-A-T graph plus a LocalBusiness node
     * (address + geo + opening hours from the primary store).
     */
    private function renderLocalDiscount(Page $page, LocalDiscount $discount): Response
    {
        $discount->load(['stores.hours', 'faqs', 'sources']);
        $page->load(['author', 'reviewer']);

        $seo = SeoHead::forPage($page, LocalDiscountSchema::build($page, $discount));

        return response()->view('pages.local-discount', [
            'page' => $page,
            'discount' => $discount,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A local-discount rollup hub, split by url depth: `/discounts/` lists states,
     * `/discounts/{state}/` lists that state's cities, and `/discounts/{state}/{city}/`
     * lists that city's businesses. The rollup is read at request time; the JSON-LD is
     * Breadcrumb + Article + WebSite + ItemList.
     */
    private function renderLocalDiscountHub(Page $page): Response
    {
        $segments = array_values(array_filter(explode('/', $page->url_path), static fn (string $s): bool => $s !== ''));
        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Local Discounts', 'url' => '/discounts/'],
        ];

        if (count($segments) <= 1) {
            $heading = 'Local Military & Veteran Discounts by State';
            $items = $this->localDiscounts->states()->map(static fn (array $s): array => [
                'url' => "/discounts/{$s['state']}/",
                'name' => $s['state_name'],
                'meta' => $s['count'].' listed',
            ])->values()->all();
        } elseif (count($segments) === 2) {
            $state = $segments[1];
            $inState = $this->localDiscounts->forState($state);
            $firstInState = $inState->first();
            $stateName = $firstInState === null ? $state : $firstInState->state_name;
            $crumbs[] = ['name' => $stateName, 'url' => "/discounts/{$state}/"];
            $heading = "Military & Veteran Discounts in {$stateName}";
            // One entry per distinct city (unique keeps the first row per city).
            $items = $inState->unique('city')
                ->map(static fn (LocalDiscount $ld): array => [
                    'url' => "/discounts/{$ld->state}/{$ld->city}/",
                    'name' => $ld->city_name,
                    'meta' => $inState->where('city', $ld->city)->count().' listed',
                ])
                ->sortBy('name')
                ->values()
                ->all();
        } else {
            [$state, $city] = [$segments[1], $segments[2]];
            $inCity = $this->localDiscounts->forCity($state, $city);
            $first = $inCity->first();
            if ($first === null) {
                return $this->renderShell($page); // hub with no live children → shell
            }
            $crumbs[] = ['name' => $first->state_name, 'url' => "/discounts/{$state}/"];
            $crumbs[] = ['name' => $first->city_name, 'url' => "/discounts/{$state}/{$city}/"];
            $heading = "Military & Veteran Discounts in {$first->city_name}, {$first->state_abbr}";
            $items = $inCity->map(static fn (LocalDiscount $ld): array => [
                'url' => "/discounts/{$ld->state}/{$ld->city}/{$ld->business_slug}/",
                'name' => $ld->company,
                'meta' => $ld->headline_discount,
            ])->values()->all();
        }

        /** @var list<array{url: string, name: string}> $schemaItems */
        $schemaItems = array_map(
            static fn (array $i): array => ['url' => $i['url'], 'name' => $i['name']],
            $items,
        );
        $seo = SeoHead::forPage($page, LocalDiscountHubSchema::build($page, $heading, $crumbs, $schemaItems));

        return response()->view('pages.local-discount-hub', [
            'page' => $page,
            'crumbs' => $crumbs,
            'heading' => $heading,
            'items' => $items,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A single naval-base page (`/navy-bases/{slug}/`). The JSON-LD graph
     * (Breadcrumb + Article + Place + GovernmentOrganization + FAQPage) is built
     * from the base; FAQs and sources feed both the visible sections and the schema.
     */
    private function renderBase(Page $page, Base $base): Response
    {
        $base->load(['faqs', 'sources']);

        $seo = SeoHead::forPage($page, BasePageSchema::build($page, $base));

        return response()->view('pages.base', [
            'page' => $page,
            'base' => $base,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The `/navy-ranks/` list page — every officer + enlisted rank on one page, in
     * three paygrade-ordered sections (per-entry anchors). The JSON-LD carries two
     * ItemLists (officer, enlisted); the officer list concatenates commissioned +
     * warrant to match the legacy graph.
     */
    private function renderRankList(Page $page): Response
    {
        $commissioned = $this->ranks->forCategoryByPaygrade(RankCategory::OfficerCommissioned);
        $warrant = $this->ranks->forCategoryByPaygrade(RankCategory::OfficerWarrant);
        $enlisted = $this->ranks->forCategoryByPaygrade(RankCategory::EnlistedPaygrade);

        $seo = SeoHead::forPage($page, RankListSchema::build($page, '/navy-ranks/', 'Navy Ranks', [
            'U.S. Navy Officer Ranks' => $commissioned->concat($warrant),
            'U.S. Navy Enlisted Paygrades' => $enlisted,
        ]));

        return response()->view('pages.rank-list', [
            'page' => $page,
            'commissioned' => $commissioned,
            'warrant' => $warrant,
            'enlisted' => $enlisted,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The `/navy-ratings/` list page — every enlisted rating on one page: active
     * ratings grouped by community (community anchors) plus a historic section. Two
     * ItemLists (active, historic) in the JSON-LD.
     */
    private function renderRatingList(Page $page): Response
    {
        $active = $this->ranks->activeRatings();
        $historic = $this->ranks->historicRatings();

        $seo = SeoHead::forPage($page, RankListSchema::build($page, '/navy-ratings/', 'Navy Ratings', [
            'U.S. Navy Active Enlisted Ratings' => $active,
            'U.S. Navy Historic Enlisted Ratings' => $historic,
        ]));

        // Group active ratings by community for the sectioned display; the view walks
        // RatingCommunity in its canonical order and renders the non-empty groups.
        $activeByCommunity = $active->groupBy(static function (Rank $rank): string {
            $community = $rank->rating_community;

            return $community === null ? '' : $community->value;
        });

        return response()->view('pages.rating-list', [
            'page' => $page,
            'activeByCommunity' => $activeByCommunity,
            'historic' => $historic,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A single air-show guide (`/air-show/{slug}/`). Emits the guide graph
     * (Article + WebPage + author/reviewer Person + FAQPage) and, when the show is
     * published with a confirmed date and no canonical override, an Event node.
     */
    private function renderAirShow(Page $page, AirShow $show): Response
    {
        // Defensive gate: a show unpublished after its page was generated must not keep
        // serving the guide (page generation only ever creates/updates, never prunes).
        if (! $show->published) {
            return $this->renderShell($page);
        }

        $show->load(['faqs', 'sources']);
        $page->load(['author', 'reviewer']);

        $seo = SeoHead::forPage($page, AirShowPageSchema::buildDetail($page, $show));

        return response()->view('pages.air-show', [
            'page' => $page,
            'show' => $show,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The air-show hub (`/air-show/`): the published-show directory + JSON-LD ItemList.
     */
    private function renderAirShowHub(Page $page, AirShowHubMeta $hub): Response
    {
        $hub->load('faqs');
        $shows = $this->airShows->published();

        $seo = SeoHead::forPage($page, AirShowPageSchema::buildHub($page, $hub, $shows));

        return response()->view('pages.air-show-hub', [
            'page' => $page,
            'hub' => $hub,
            'shows' => $shows,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A jet-team hub (`/{team}/`): the season schedule directory + JSON-LD ItemList.
     */
    private function renderJetTeamHub(Page $page, JetTeam $team): Response
    {
        $team->load('faqs');
        $schedule = $this->jetTeams->schedule($team->team);
        // Slugs that have a published city guide, so the schedule table links only
        // stops that resolve to a real page.
        $guideSlugs = $this->jetTeams->publishedCities($team->team)->pluck('slug')->all();

        $seo = SeoHead::forPage($page, JetTeamPageSchema::buildHub($page, $team, $schedule));

        return response()->view('pages.jet-team-hub', [
            'page' => $page,
            'team' => $team,
            'schedule' => $schedule,
            'guideSlugs' => $guideSlugs,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A jet-team city guide (`/{team}/{slug}/`). Emits the guide graph + a show-stop
     * Event. Defensive published gate: a city unpublished after its page was generated
     * falls through to the shell (findCity doesn't filter published).
     */
    private function renderJetTeamCity(Page $page, JetTeamCity $city): Response
    {
        if (! $city->published) {
            return $this->renderShell($page);
        }

        $city->load(['team', 'faqs', 'sources']);
        $page->load(['author', 'reviewer']);

        $seo = SeoHead::forPage($page, JetTeamPageSchema::buildCity($page, $city, $city->team));

        return response()->view('pages.jet-team-city', [
            'page' => $page,
            'city' => $city,
            'team' => $city->team,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A Navy Week city page (`/city/{slug}/`). Emits Breadcrumb + two
     * GovernmentOrganization nodes + the rich Event (with per-day subEvents) + FAQPage.
     */
    private function renderNavyWeekCity(Page $page, NavyWeekEvent $event): Response
    {
        $event->load('faqs');

        $seo = SeoHead::forPage($page, NavyWeekCitySchema::build($page, $event));

        return response()->view('pages.navy-week-city', [
            'page' => $page,
            'event' => $event,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * A single fleet-week city guide (`/fleetweek/{slug}/`). Emits the guide graph +
     * a Festival node when the city has an official event (Tier-3 cities omit it).
     */
    private function renderFleetWeek(Page $page, FleetWeek $week): Response
    {
        $week->load(['faqs', 'sources']);
        $page->load(['author', 'reviewer']);

        $seo = SeoHead::forPage($page, FleetWeekPageSchema::buildDetail($page, $week));

        return response()->view('pages.fleet-week', [
            'page' => $page,
            'week' => $week,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The fleet-week hub (`/fleetweek/`): the city directory + JSON-LD ItemList. The
     * hub FAQs live on the page's own polymorphic `faqs` (seeded from the legacy consts).
     */
    private function renderFleetWeekHub(Page $page): Response
    {
        $page->load('faqs');
        $weeks = $this->fleetWeeks->all();

        $seo = SeoHead::forPage($page, FleetWeekPageSchema::buildHub($page, $weeks, $page->faqs));

        return response()->view('pages.fleet-week-hub', [
            'page' => $page,
            'weeks' => $weeks,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The category hub (`/discount/{slug}/`): the ordered brand grid for one
     * category. `orderedConnections` gives the legacy sort; a brand is shown only
     * when it has a published discount-brand page (the card links straight to it).
     */
    private function renderDiscountCategory(Page $page, DiscountCategory $category): Response
    {
        $ordered = $this->categories->orderedConnections($category);

        // The live discount-brand pages for this category's connections (repository
        // owns the query; a brand shows only when it has a published page).
        $connectionIds = $ordered->map(static fn (Connection $connection): int => $connection->id)->all();
        $brandPages = $this->pages->liveDiscountBrandPagesForConnections($connectionIds);

        /** @var array<int, array{url: string, audience: string|null}> $liveByConnectionId */
        $liveByConnectionId = [];
        foreach ($brandPages as $brandPage) {
            $offer = $brandPage->pageable;
            // First live page per connection wins (pages are id-ordered), so the card
            // is deterministic if a connection ever has more than one published page.
            if ($offer instanceof Offer && ! isset($liveByConnectionId[$offer->connection_id])) {
                $liveByConnectionId[$offer->connection_id] = [
                    'url' => $brandPage->url_path,
                    'audience' => $offer->audience_label,
                ];
            }
        }

        // Keep the repository's ordering; drop brands with no live page.
        $brands = $ordered
            ->filter(static fn (Connection $c): bool => isset($liveByConnectionId[$c->id]))
            ->map(static fn (Connection $c): array => [
                'brand' => $c->brand,
                'logo_url' => $c->logo_url,
                'url' => $liveByConnectionId[$c->id]['url'],
                'audience' => $liveByConnectionId[$c->id]['audience'],
            ])
            ->values();

        // ItemList entries (absolute URL + display name) for the JSON-LD.
        $brandItems = array_values($brands->map(static fn (array $b): array => [
            'url' => SeoUrl::absolute($b['url']),
            'name' => $b['audience'] !== null && $b['audience'] !== ''
                ? "{$b['brand']} {$b['audience']} Discount"
                : "{$b['brand']} Military & Veteran Discount",
        ])->all());

        $seo = SeoHead::forPage($page, DiscountCategorySchema::build($page, $category, $brandItems));

        return response()->view('pages.discount-category', [
            'page' => $page,
            'category' => $category,
            'brands' => $brands,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }
}
