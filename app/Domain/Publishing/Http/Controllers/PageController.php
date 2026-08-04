<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Controllers;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\LocalDiscountRepositoryInterface;
use App\Domain\Catalog\Repositories\VeteransDayMealRepositoryInterface;
use App\Domain\Catalog\Support\VeteransDayFreeMealsPresenter;
use App\Domain\Crm\Models\Connection;
use App\Domain\Pillars\Enums\NavyWeekStatus;
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
use App\Domain\Pillars\Repositories\BaseRepositoryInterface;
use App\Domain\Pillars\Repositories\FleetWeekRepositoryInterface;
use App\Domain\Pillars\Repositories\JetTeamRepositoryInterface;
use App\Domain\Pillars\Repositories\NavyWeekEventRepositoryInterface;
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
use App\Domain\Publishing\Seo\AuthorPageSchema;
use App\Domain\Publishing\Seo\ContentPageSchema;
use App\Domain\Publishing\Seo\DiscountCategorySchema;
use App\Domain\Publishing\Seo\DiscountGuideSchema;
use App\Domain\Publishing\Seo\DiscountIndexSchema;
use App\Domain\Publishing\Seo\HomePageSchema;
use App\Domain\Publishing\Seo\LocalDiscountHubSchema;
use App\Domain\Publishing\Seo\LocalDiscountSchema;
use App\Domain\Publishing\Seo\SeoHead;
use App\Domain\Publishing\Seo\SeoUrl;
use App\Domain\Publishing\Seo\VeteransDayFreeMealsSchema;
use App\Domain\Publishing\Support\PagePaths;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
        private readonly VeteransDayMealRepositoryInterface $meals,
        private readonly NavyWeekEventRepositoryInterface $navyWeekEvents,
        private readonly BaseRepositoryInterface $bases,
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
            // The site root: a data-driven landing (schedule from the pillar), no pageable.
            PageType::Home => $this->renderHome($page),
            PageType::DiscountBrand => $pageable instanceof Offer
                ? $this->renderDiscountGuide($page, $pageable)
                : null,
            PageType::DiscountCategoryHub => $pageable instanceof DiscountCategory
                ? $this->renderDiscountCategory($page, $pageable)
                : null,
            PageType::Base => $pageable instanceof Base
                ? $this->renderBase($page, $pageable)
                : null,
            // The bases hubs own no pageable — like the rank/rating lists they
            // aggregate the whole bases pillar at render, keyed by the page slug.
            PageType::BaseHub => $this->renderBaseHub($page),
            PageType::BaseOverseasHub => $this->renderBaseOverseasHub($page),
            PageType::BaseStateHub => $this->renderBaseRegionHub($page, 'state'),
            PageType::BaseCountryHub => $this->renderBaseRegionHub($page, 'country'),
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
            // Author profile page (pageable = the byline User): ProfilePage/Person graph.
            PageType::Author => $pageable instanceof User
                ? $this->renderAuthor($page, $pageable)
                : null,
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
            'veterans-day-free-meals' => $this->renderVeteransDayFreeMeals($page),
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
     * An `/authors/{slug}/` author profile page. The page's `pageable` is the byline
     * User; the graph is Person (mainEntity) + Breadcrumb + ProfilePage + an ItemList
     * of the articles they've authored. The visible "writes for" / "reviews for" lists
     * come from the pages that cite this user as author / reviewer.
     */
    private function renderAuthor(Page $page, User $author): Response
    {
        // A cleared profile slug retires the public profile: it no longer has a canonical
        // /authors/{slug}/ identity, so it stops serving the rich profile (defensive gate,
        // mirroring the published-gate pattern on air-show/jet-team-city).
        if ($author->slug === null || $author->slug === '') {
            return $this->renderShell($page);
        }

        $authored = $this->pages->publishedIndexableAuthoredBy($author->id);
        $reviewed = $this->pages->publishedIndexableReviewedBy($author->id);

        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => $author->name, 'url' => $page->url_path],
        ];

        $seo = SeoHead::forPage($page, AuthorPageSchema::build($page, $author, $crumbs, $authored));

        return response()->view('pages.author', [
            'page' => $page,
            'author' => $author,
            'authored' => $authored,
            'reviewed' => $reviewed,
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The home landing page (`/`), a 1:1 port of the legacy `Home.tsx`. A data-driven hub:
     * the 12-city Navy Week schedule + the current/next stop are read live from the pillar,
     * so the page stores no body (only its FAQs, on the polymorphic `faqs`). The JSON-LD is
     * WebSite + Breadcrumb + two GovernmentOrganizations + the schedule ItemList + FAQPage
     * ({@see HomePageSchema}). `currentOrNext` mirrors the legacy `getActiveEvent() ||
     * getNextEvent()`: the first Active stop, else the first Upcoming one.
     */
    private function renderHome(Page $page): Response
    {
        $page->load('faqs');
        $events = $this->navyWeekEvents->all();

        $activeEvent = $events->firstWhere('status', NavyWeekStatus::Active);
        $currentOrNext = $activeEvent ?? $events->firstWhere('status', NavyWeekStatus::Upcoming);

        // A stop counts toward the "first-time locations" total if it is a full first-time
        // host OR introduces a new first-time location (the model owns the rule).
        $firstTimeCount = $events
            ->filter(static fn (NavyWeekEvent $e): bool => $e->isFirstTimeLocation())
            ->count();

        $seo = SeoHead::forPage($page, HomePageSchema::build($page, $events, $page->faqs));

        return response()->view('pages.home', [
            'page' => $page,
            'events' => $events,
            'activeEvent' => $activeEvent,
            'currentOrNext' => $currentOrNext,
            'firstTimeCount' => $firstTimeCount,
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

    /**
     * The `/veterans-day/free-meals/` roundup: the offers table + JSON-LD
     * Breadcrumb + Article + author Person + ItemList + FAQPage. The table, stats,
     * ItemList, and FAQ answers are all computed live from the `verified()` meals
     * (YMYL gate: verified + primary source), so the page tracks the data, not a
     * stored body. dateModified follows the freshest verification (legacy parity).
     */
    private function renderVeteransDayFreeMeals(Page $page): Response
    {
        $page->load('author');
        $meals = $this->meals->verified();
        $presenter = new VeteransDayFreeMealsPresenter($meals);

        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Veterans Day', 'url' => '/veterans-day/'],
            ['name' => 'Free Meals 2026', 'url' => $page->url_path],
        ];
        $faqs = $presenter->faqs();
        $dateModified = $presenter->dateModified() !== ''
            ? $presenter->dateModified()
            : ($page->date_published?->format('Y-m-d') ?? '');

        $seo = SeoHead::forPage($page, VeteransDayFreeMealsSchema::build(
            $page,
            $crumbs,
            (string) $page->meta_description,
            $presenter->itemListEntries(),
            $dateModified,
            $faqs,
        ));

        return response()->view('pages.veterans-day-free-meals', [
            'page' => $page,
            'crumbs' => $crumbs,
            'meals' => $meals,
            'stats' => $presenter->stats(),
            'lastUpdatedLabel' => $presenter->lastUpdatedLabel(),
            'faqs' => $faqs,
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
        // The hub level + its state/city come from the stable generation_key, not the
        // (renameable) url_path: "local-hub:root" | "local-hub:state:{state}" |
        // "local-hub:city:{state}:{city}". Every link is built via PagePaths so the whole
        // family tracks config('publishing.paths.local_discounts').
        $parts = explode(':', (string) $page->generation_key);
        $level = $parts[1] ?? 'root';
        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Local Discounts', 'url' => PagePaths::root('local_discounts')],
        ];

        if ($level === 'root') {
            $heading = 'Local Military & Veteran Discounts by State';
            $items = $this->localDiscounts->states()->map(static fn (array $s): array => [
                'url' => PagePaths::child('local_discounts', $s['state']),
                'name' => $s['state_name'],
                'meta' => $s['count'].' listed',
            ])->values()->all();
        } elseif ($level === 'state') {
            $state = $parts[2] ?? '';
            $inState = $this->localDiscounts->forState($state);
            $firstInState = $inState->first();
            $stateName = $firstInState === null ? $state : $firstInState->state_name;
            $crumbs[] = ['name' => $stateName, 'url' => $page->url_path];
            $heading = "Military & Veteran Discounts in {$stateName}";
            // One entry per distinct city (unique keeps the first row per city).
            $items = $inState->unique('city')
                ->map(static fn (LocalDiscount $ld): array => [
                    'url' => PagePaths::child('local_discounts', $ld->state, $ld->city),
                    'name' => $ld->city_name,
                    'meta' => $inState->where('city', $ld->city)->count().' listed',
                ])
                ->sortBy('name')
                ->values()
                ->all();
        } else {
            $state = $parts[2] ?? '';
            $city = $parts[3] ?? '';
            $inCity = $this->localDiscounts->forCity($state, $city);
            $first = $inCity->first();
            if ($first === null) {
                return $this->renderShell($page); // hub with no live children → shell
            }
            $crumbs[] = ['name' => $first->state_name, 'url' => PagePaths::child('local_discounts', $state)];
            $crumbs[] = ['name' => $first->city_name, 'url' => $page->url_path];
            $heading = "Military & Veteran Discounts in {$first->city_name}, {$first->state_abbr}";
            $items = $inCity->map(static fn (LocalDiscount $ld): array => [
                'url' => PagePaths::child('local_discounts', $ld->state, $ld->city, $ld->business_slug),
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
    /**
     * `/navy-bases/` — the directory root: browse-by-state, overseas, and the A–Z list.
     */
    private function renderBaseHub(Page $page): Response
    {
        $all = $this->bases->all()->sortBy('name')->values();

        return response()->view('pages.base-hub', [
            'page' => $page,
            'states' => $this->groupBases($all, static fn (Base $b): ?string => $b->state),
            'countries' => $this->groupBases($all, static fn (Base $b): ?string => $b->country_slug),
            'allBases' => $all,
        ] + $this->seoVars($page));
    }

    /**
     * `/navy-bases/overseas/` — the overseas rollup, grouped by combatant command.
     */
    private function renderBaseOverseasHub(Page $page): Response
    {
        $overseas = $this->bases->all()
            ->filter(static fn (Base $b): bool => filled($b->country_slug))
            ->sortBy('name')
            ->values();

        return response()->view('pages.base-overseas-hub', [
            'page' => $page,
            'countries' => $this->groupBases($overseas, static fn (Base $b): ?string => $b->country_slug),
            'byRegion' => $overseas->groupBy(static fn (Base $b): string => $b->region?->label() ?? 'Other')->sortKeys(),
            'allBases' => $overseas,
        ] + $this->seoVars($page));
    }

    /**
     * `/navy-bases/{state|country}/` — one region's installations, grouped by base
     * type. The region is carried by the page slug (these hubs own no pageable).
     */
    private function renderBaseRegionHub(Page $page, string $kind): ?Response
    {
        $bases = $kind === 'state'
            ? $this->bases->forState($page->slug)
            : $this->bases->forCountry($page->slug);

        if ($bases->isEmpty()) {
            return null;
        }

        $first = $bases->first();

        return response()->view($kind === 'state' ? 'pages.base-state-hub' : 'pages.base-country-hub', [
            'page' => $page,
            'regionName' => $kind === 'state' ? (string) $first->state_name : (string) $first->country,
            'grouped' => $bases->sortBy('name')
                ->groupBy(static fn (Base $b): string => Str::plural($b->type->label()))
                ->sortKeys(),
            'hostNationContext' => $kind === 'country' ? $first->host_nation_context : null,
        ] + $this->seoVars($page));
    }

    /**
     * The SEO view vars every page body needs: the serialized head block plus the
     * robots flag the base layout reads. Hubs that build no bespoke JSON-LD graph
     * use this directly.
     *
     * @return array{seoHead: string, noindex: bool}
     */
    private function seoVars(Page $page): array
    {
        $seo = SeoHead::forPage($page);

        return ['seoHead' => $seo->render(), 'noindex' => $seo->isNoindex()];
    }

    /**
     * Group bases by a region column, dropping rows with no value, keyed by slug.
     *
     * @param  Collection<int, Base>  $bases
     * @param  callable(Base): ?string  $region
     * @return Collection<string, Collection<int, Base>>
     */
    private function groupBases(Collection $bases, callable $region): Collection
    {
        return $bases->filter(static fn (Base $b): bool => filled($region($b)))
            ->groupBy(static fn (Base $b): string => (string) $region($b))
            ->sortKeys();
    }

    private function renderRankList(Page $page): Response
    {
        $commissioned = $this->ranks->forCategoryByPaygrade(RankCategory::OfficerCommissioned);
        $warrant = $this->ranks->forCategoryByPaygrade(RankCategory::OfficerWarrant);
        $enlisted = $this->ranks->forCategoryByPaygrade(RankCategory::EnlistedPaygrade);

        $seo = SeoHead::forPage($page, RankListSchema::build($page, $page->url_path, 'Navy Ranks', [
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

        $seo = SeoHead::forPage($page, RankListSchema::build($page, $page->url_path, 'Navy Ratings', [
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
