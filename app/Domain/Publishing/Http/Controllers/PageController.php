<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Controllers;

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\LocalDiscountRepositoryInterface;
use App\Domain\Catalog\Repositories\VeteransDayMealRepositoryInterface;
use App\Domain\Catalog\Support\DiscountCategoryOrdering;
use App\Domain\Catalog\Support\VeteransDayFreeMealsPresenter;
use App\Domain\Crm\Models\Connection;
use App\Domain\Pillars\Enums\BaseType;
use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Enums\DesignatorCommunity;
use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Pillars\Models\OverseasCountry;
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
            // Officer designators: hub + community hubs aggregate the pillar; the
            // detail page carries its Rank (category `officer-designator`).
            PageType::NavyReferenceHub => $this->renderNavyReferenceHub($page),
            // Navy Week programme pages — both aggregate the navy-week pillar.
            PageType::Schedule => $this->renderSchedulePage($page, 'pages.schedule'),
            PageType::RouteMap => $this->renderSchedulePage($page, 'pages.route-map'),
            PageType::DesignatorHub => $this->renderDesignatorHub($page),
            PageType::DesignatorCommunityHub => $this->renderDesignatorCommunityHub($page),
            PageType::Designator => $pageable instanceof Rank
                ? $this->renderDesignator($page, $pageable)
                : null,
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
            // The three policy pages carry a BreadcrumbList in their JSON-LD but
            // render no visible breadcrumb trail (they open straight on the h1).
            'privacy' => $this->renderContentPage($page, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Privacy Policy', 'url' => '/privacy/'],
            ], showCrumbs: false),
            'terms' => $this->renderContentPage($page, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Terms of Use', 'url' => '/terms/'],
            ], showCrumbs: false),
            'contact' => $this->renderContentPage($page, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Contact Us', 'url' => '/contact/'],
            ], showCrumbs: false),
            'our-process' => $this->renderContentPage($page, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Our Process', 'url' => '/our-process/'],
            ]),
            'best-credit-cards-for-military' => $this->renderContentPage($page, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Best Credit Cards for Military', 'url' => '/best-credit-cards-for-military/'],
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
        $page->load(['author', 'reviewer', 'faqs']);
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
            'faqs' => $page->faqs,
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
    private function renderContentPage(Page $page, array $crumbs, bool $showCrumbs = true): Response
    {
        $seo = SeoHead::forPage($page, ContentPageSchema::build($page, $crumbs));

        return response()->view('pages.content', [
            'page' => $page,
            'crumbs' => $crumbs,
            'showCrumbs' => $showCrumbs,
            'heading' => (string) $page->title,
            'blocks' => $page->body_blocks ?? [],
            'faqs' => $page->faqs,
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
            'faqs' => $page->faqs,
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

        // The legacy directory sorts the whole catalogue by company name under the
        // browser's default collation — punctuation-first, so "’47 Brand" leads.
        $collator = class_exists(\Collator::class) ? new \Collator('en_US') : null;
        $cards = $this->discountBrandCards($brandPages);
        usort($cards, static fn (array $a, array $b): int => $collator !== null
            ? (int) $collator->compare($a['brand'], $b['brand'])
            : strcmp($a['brand'], $b['brand']));

        return response()->view('pages.discount-index', [
            'page' => $page,
            'brands' => collect($cards),
            // The category hubs the live directory links from "Browse by category",
            // each with the brand count its own hub will list (live pages only).
            'categories' => $this->categories->all()
                ->map(fn (DiscountCategory $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => count($this->liveCategoryBrands($category)),
                ])
                ->all(),
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The per-brand logo chip colour (the legacy `logoBackground`) — but only if the
     * stored value really is a colour.
     *
     * Editor-supplied and rendered into a `style` attribute, where Blade's HTML
     * escaping stops an attribute break-out but not CSS injection
     * (`#fff; background-image: url(…)`). Restricting it to a 3/6-digit hex literal
     * means a stored string can never become anything but a colour. Mirrors the same
     * guard the shared Deals chrome applies (ChromeCatalog::hexColour).
     */
    private static function logoChipColour(?string $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value) === 1 ? $value : '#ffffff';
    }

    /**
     * Card data for a list of discount-brand pages: the brand, its logo + chip
     * colour, the per-brand logo cap, and the headline discount.
     *
     * @param  Collection<int, Page>  $brandPages
     * @return list<array{slug: string, brand: string, url: string, category: string|null, headline: string|null, logo_url: string|null, logo_background: string, logo_max_height: int, logo_max_width: int}>
     */
    private function discountBrandCards(Collection $brandPages): array
    {
        $cards = [];

        foreach ($brandPages as $brandPage) {
            $offer = $brandPage->pageable;
            if (! $offer instanceof Offer) {
                continue;
            }

            $connection = $offer->connection;
            $cap = $connection->logo_display ?? ['cardMaxHeight' => 28, 'cardMaxWidth' => 130];

            $cards[] = [
                'slug' => (string) $brandPage->slug,
                'brand' => $connection->brand,
                'url' => (string) $brandPage->url_path,
                'category' => $connection->category,
                'headline' => $offer->headline_discount,
                'logo_url' => $connection->logo_url,
                'logo_background' => self::logoChipColour($connection->logo_background),
                'logo_max_height' => $cap['cardMaxHeight'],
                'logo_max_width' => $cap['cardMaxWidth'],
            ];
        }

        return $cards;
    }

    /**
     * The category's ordered connections that actually have a published brand page,
     * as [connection id => page], keeping the repository's curated order.
     *
     * @return array<int, Page>
     */
    private function liveCategoryBrands(DiscountCategory $category): array
    {
        $ordered = $this->categories->orderedConnections($category);
        $brandPages = $this->pages->liveDiscountBrandPagesForConnections(
            $ordered->map(static fn (Connection $connection): int => $connection->id)->all()
        );

        /** @var array<int, Page> $byConnectionId */
        $byConnectionId = [];
        foreach ($brandPages as $brandPage) {
            $offer = $brandPage->pageable;
            // First live page per connection wins (pages are id-ordered), so the card
            // is deterministic if a connection ever has more than one published page.
            if ($offer instanceof Offer && ! isset($byConnectionId[$offer->connection_id])) {
                $byConnectionId[$offer->connection_id] = $brandPage;
            }
        }

        $live = [];
        foreach ($ordered as $connection) {
            if (isset($byConnectionId[$connection->id])) {
                $live[$connection->id] = $byConnectionId[$connection->id];
            }
        }

        // The curated lists hold PAGE slugs, so they can only be applied here, where
        // the page is in hand — the repository returns the brand A–Z baseline.
        $bySlug = [];
        foreach ($live as $connectionId => $brandPage) {
            $bySlug[(string) $brandPage->slug] = $connectionId;
        }

        $ordered = [];
        foreach (DiscountCategoryOrdering::apply($category, $bySlug) as $connectionId) {
            $ordered[$connectionId] = $live[$connectionId];
        }

        return $ordered;
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

        // Per-brand logo cap; the hero chip scales the card cap by a fixed factor
        // (legacy src/data/discounts/logo.ts — LOGO_DISPLAY_DEFAULT + LOGO_HERO_SCALE).
        $cap = $offer->connection->logo_display ?? ['cardMaxHeight' => 28, 'cardMaxWidth' => 130];

        return response()->view('pages.discount', [
            'page' => $page,
            'offer' => $offer,
            'logoHero' => [
                'maxHeight' => (int) round($cap['cardMaxHeight'] * 1.4),
                'maxWidth' => (int) round($cap['cardMaxWidth'] * 1.4),
                'background' => self::logoChipColour($offer->connection->logo_background),
            ],
            // "Ask the brand" share block: advisory (no first-party discount) pages
            // only, unless a record forces it either way via `share_cta.enabled`.
            'showShareCta' => (bool) ($offer->share_cta['enabled']
                ?? $offer->offer_type === OfferType::AdvisoryNoDiscount),
            'share' => $this->discountShareContent($page, $offer),
            'relatedBrands' => $this->relatedDiscountBrands($page, $offer),
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * The pre-composed "ask the brand for a military discount" share content —
     * ported 1:1 from the legacy src/data/discounts/share.ts. Everything is derived
     * from the record so the block stays static HTML with zero client JavaScript.
     *
     * @return array{headline: string, blurb: string, postText: string, instagramCaption: string, xIntentUrl: string, facebookUrl: string}
     */
    private function discountShareContent(Page $page, Offer $offer): array
    {
        $cfg = $offer->share_cta ?? [];
        $brand = $offer->connection->brand;
        $pageUrl = SeoUrl::absolute((string) $page->url_path);
        // A stored override only wins when it really is a non-empty string.
        $override = static fn (string $key, string $default): string => isset($cfg[$key])
            && is_string($cfg[$key]) && $cfg[$key] !== '' ? $cfg[$key] : $default;

        /** @var list<string> $hashtags */
        $hashtags = isset($cfg['hashtags']) && is_array($cfg['hashtags']) && $cfg['hashtags'] !== []
            ? array_values(array_filter($cfg['hashtags'], 'is_string'))
            : ['MilitaryDiscount', 'VeteranDiscount', 'Veterans'];
        $hashtagLine = implode(' ', array_map(static fn (string $h): string => '#'.$h, $hashtags));

        $body = $override('message', "I served — and NavyWeek.org confirms {$brand} still has no military or veteran discount. {$brand}, those who served would shop with you for one. 🇺🇸");
        $postText = trim("{$body} {$hashtagLine}");
        $igHashtags = implode(' ', array_map(
            static fn (string $h): string => '#'.$h,
            [...$hashtags, 'Military', 'ThankYouForYourService'],
        ));

        return [
            'headline' => $override('headline', "Ask {$brand} for a military discount"),
            'blurb' => $override('blurb', "{$brand} doesn't offer a military or veteran discount yet. Public demand is what changes that — post the ask, tag {$brand}, and every share points the next person who searches back to the honest answer here."),
            'postText' => $postText,
            'instagramCaption' => $body."\n\nSee the brands that DO honor the military at navyweek.org.\n\n".$igHashtags,
            'xIntentUrl' => 'https://twitter.com/intent/tweet?text='.rawurlencode($postText).'&url='.rawurlencode($pageUrl),
            'facebookUrl' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($pageUrl),
        ];
    }

    /**
     * "More military discounts" — the guide's four related cards. The legacy view
     * lists the record's curated `relatedSlugs` first, then every other brand in
     * catalogue order (page id), and takes the first four.
     *
     * @return list<array{slug: string, brand: string, headline: string|null, url: string}>
     */
    private function relatedDiscountBrands(Page $page, Offer $offer): array
    {
        $pinned = $offer->related_slugs ?? [];
        $rank = array_flip($pinned);

        $related = $this->pages->allPublishedDiscountBrandPages()
            ->reject(static fn (Page $p): bool => $p->id === $page->id)
            // One composite key: pinned slugs first (in their listed order), then
            // catalogue order. `sortBy([...])` would treat each closure as a
            // comparator, not a key extractor — hence the single sortable string.
            ->sortBy(static fn (Page $p): string => sprintf('%06d%09d', $rank[$p->slug] ?? 999999, $p->id))
            ->take(4)
            ->map(static function (Page $p): ?array {
                $sibling = $p->pageable;

                return $sibling instanceof Offer ? [
                    'slug' => (string) $p->slug,
                    'brand' => $sibling->connection->brand,
                    'headline' => $sibling->headline_discount,
                    'url' => (string) $p->url_path,
                ] : null;
            })
            ->filter()
            ->all();

        return array_values($related);
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
     *
     * Copy + card fields are ported verbatim from the three legacy components in
     * src/page-views/LocalDiscountHubs.tsx (`LocalHub`, `LocalStateHub`, `LocalCityHub`):
     * an eyebrow on every level, a two-tone `<h1>` whose tail sits in a gold `<em>`, the
     * component's own intro paragraph (NOT the meta description), and cards carrying a
     * `sub` line (state abbr / state abbr / category) above the `meta` rollup line.
     */
    private function renderLocalDiscountHub(Page $page): Response
    {
        // The hub level + its state/city come from the stable generation_key, not the
        // (renameable) url_path: "local-hub:root" | "local-hub:state:{state}" |
        // "local-hub:city:{state}:{city}". Every link is built via PagePaths so the whole
        // family tracks config('publishing.paths.local_discounts').
        $parts = explode(':', (string) $page->generation_key);
        $level = $parts[1] ?? 'root';
        $hubRoot = PagePaths::root('local_discounts');

        // The legacy root labels its own crumb "Local Discounts"; the deeper levels link
        // back to it as "Discounts".
        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => $level === 'root' ? 'Local Discounts' : 'Discounts', 'url' => $hubRoot],
        ];
        $note = 'NavyWeek.org is an independent publisher and is not affiliated with the businesses listed here.';

        if ($level === 'root') {
            $states = $this->localDiscounts->all()
                ->groupBy('state')
                ->sortBy(static fn (Collection $recs): string => (string) $recs->first()?->state_name);

            $eyebrow = 'Local businesses · by state & city';
            $headingLead = 'Local Military Discounts ';
            $headingAccent = 'Near You';
            $heading = 'Local Military Discounts by City & State';
            $intro = 'Military and veteran discounts at local businesses — attractions, restaurants, gyms and more — organized by where they are, not by national brand. Every offer is verified against the business’s own terms and independently sourced. Pick a state to start.';
            $note = 'New cities and businesses are added deliberately as each offer is verified. '.$note;
            $items = $states->map(static function (Collection $recs): array {
                /** @var LocalDiscount $first */
                $first = $recs->first();
                $businesses = $recs->count();
                $cities = $recs->pluck('city')->unique()->count();

                return [
                    'url' => PagePaths::child('local_discounts', $first->state),
                    'name' => $first->state_name,
                    'sub' => $first->state_abbr,
                    'meta' => $businesses.' local '.($businesses === 1 ? 'business' : 'businesses')
                        .' · '.$cities.' '.($cities === 1 ? 'city' : 'cities'),
                    'go' => 'Browse '.$first->state_name.' →',
                ];
            })->values()->all();
        } elseif ($level === 'state') {
            $state = $parts[2] ?? '';
            $inState = $this->localDiscounts->forState($state);
            $firstInState = $inState->first();
            $stateName = $firstInState === null ? $state : $firstInState->state_name;
            $stateAbbr = $firstInState === null ? '' : $firstInState->state_abbr;
            $crumbs[] = ['name' => $stateName, 'url' => $page->url_path];

            $eyebrow = $stateAbbr.' · local military discounts';
            $headingLead = 'Military Discounts in ';
            $headingAccent = $stateName;
            $heading = "Military Discounts in {$stateName}";
            $intro = "Local businesses across {$stateName} that offer a military or veteran discount, grouped by city. Choose a city to see every verified local offer there.";
            $items = $inState->groupBy('city')
                ->sortBy(static fn (Collection $recs): string => (string) $recs->first()?->city_name)
                ->map(static function (Collection $recs): array {
                    /** @var LocalDiscount $first */
                    $first = $recs->first();
                    $businesses = $recs->count();

                    return [
                        'url' => PagePaths::child('local_discounts', $first->state, $first->city),
                        'name' => $first->city_name,
                        'sub' => $first->state_abbr,
                        'meta' => $businesses.' local '.($businesses === 1 ? 'business' : 'businesses'),
                        'go' => 'Browse '.$first->city_name.' →',
                    ];
                })->values()->all();
        } else {
            $state = $parts[2] ?? '';
            $city = $parts[3] ?? '';
            // The legacy city hub lists businesses in registry order, which the importer
            // preserves as the row id — not the repository's alphabetical company sort.
            // `all()` (rather than `forCity()`) because the card's meta line needs each
            // business's primary storefront, which only `all()` eager-loads.
            $inCity = $this->localDiscounts->all()
                ->where('state', $state)
                ->where('city', $city)
                ->sortBy('id')
                ->values();
            $first = $inCity->first();
            if ($first === null) {
                return $this->renderShell($page); // hub with no live children → shell
            }
            $crumbs[] = ['name' => $first->state_name, 'url' => PagePaths::child('local_discounts', $state)];
            $crumbs[] = ['name' => $first->city_name, 'url' => $page->url_path];

            $eyebrow = $first->city_name.', '.$first->state_abbr.' · local military discounts';
            $headingLead = 'Military Discounts in ';
            $headingAccent = $first->city_name;
            $heading = "Military Discounts in {$first->city_name}, {$first->state_abbr}";
            $intro = "Local businesses in {$first->city_name} that give active-duty, veterans, and military families a discount. Each guide covers the exact offer, who qualifies, and how to redeem it in person.";
            // Legacy renders `{headlineDiscount} · {locations[0]?.street}` — the separator
            // stays even when the business has no storefront row.
            $items = $inCity->map(static fn (LocalDiscount $ld): array => [
                'url' => PagePaths::child('local_discounts', $ld->state, $ld->city, $ld->business_slug),
                'name' => $ld->company,
                'sub' => $ld->category,
                'meta' => $ld->headline_discount.' · '.($ld->stores->first()->street ?? ''),
                'go' => 'See the discount →',
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
            'eyebrow' => $eyebrow,
            'headingLead' => $headingLead,
            'headingAccent' => $headingAccent,
            'intro' => $intro,
            'items' => $items,
            'note' => $note,
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

        // "Nearby bases" = the base's own curated slug list, then up to three other
        // installations in the same state/country (NavyBaseDetail.tsx L90-93). The
        // legacy registry order is the insertion order, which the import preserves
        // as the row id — hence sortBy('id') rather than the repository's name sort.
        $siblings = $base->isOverseas()
            ? $this->bases->forCountry((string) $base->country_slug)
            : $this->bases->forState((string) $base->state);
        $nearbySlugs = $base->nearby_bases ?? [];
        $nearby = $this->bases->all()
            ->whereIn('slug', $nearbySlugs)
            ->sortBy(static fn (Base $b): int => (int) array_search($b->slug, $nearbySlugs, true))
            ->values();

        return response()->view('pages.base', [
            'page' => $page,
            'base' => $base,
            'nearby' => $nearby,
            'otherInRegion' => $siblings
                ->reject(static fn (Base $b): bool => $b->slug === $base->slug)
                ->sortBy('id')
                ->take(3)
                ->values(),
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
        $overseas = $all->filter(static fn (Base $b): bool => filled($b->country_slug));

        return response()->view('pages.base-hub', [
            'page' => $page,
            'states' => $this->statesWithBases($all),
            'countries' => $this->countriesWithBases($all),
            'baseTypes' => $this->baseTypesPresent($all),
            'basesTotal' => $all->count(),
            'overseasTotal' => $overseas->count(),
            'allBases' => $all,
        ] + $this->seoVars($page));
    }

    /**
     * `/navy-bases/overseas/` — the overseas rollup, grouped by combatant command.
     */
    private function renderBaseOverseasHub(Page $page): Response
    {
        $all = $this->bases->all()->sortBy('name')->values();
        $overseas = $all->filter(static fn (Base $b): bool => filled($b->country_slug))->values();
        $countries = $this->countriesWithBases($overseas);

        // Country cards are grouped by combatant command, commands ordered by their
        // enum value (NavyBasesOverseas.tsx L145-152).
        $byRegion = [];
        foreach ($countries as $country) {
            $byRegion[$country['region']]['value'] = $country['region'];
            $byRegion[$country['region']]['label'] = $country['regionLabel'];
            $byRegion[$country['region']]['countries'][] = $country;
        }
        ksort($byRegion);

        return response()->view('pages.base-overseas-hub', [
            'page' => $page,
            'countries' => $countries,
            'byRegion' => array_values($byRegion),
            'regionOptions' => array_reduce(
                CombatantCommand::cases(),
                static fn (array $carry, CombatantCommand $c): array => $carry + [$c->value => $c->label()],
                [],
            ),
            'basesTotal' => $all->count(),
            'overseasTotal' => $overseas->count(),
            'allBases' => $overseas,
        ] + $this->seoVars($page));
    }

    /**
     * US states that have at least one base, name-ordered with a count — the port of
     * `getStatesWithBases()` (src/data/bases/index.ts L196).
     *
     * @param  Collection<int, Base>  $bases
     * @return list<array{slug: string, name: string, abbr: string, count: int}>
     */
    private function statesWithBases(Collection $bases): array
    {
        $states = [];
        foreach ($this->groupBases($bases, static fn (Base $b): ?string => $b->state) as $group) {
            $first = $group->first();
            if (! $first instanceof Base) {
                continue;
            }
            $states[] = [
                'slug' => (string) $first->state,
                'name' => (string) $first->state_name,
                'abbr' => (string) $first->state_abbr,
                'count' => $group->count(),
            ];
        }
        usort($states, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $states;
    }

    /**
     * Host countries that have at least one base, name-ordered with a count — the
     * port of `getCountries()` (src/data/bases/index.ts L173).
     *
     * @param  Collection<int, Base>  $bases
     * @return list<array{slug: string, name: string, iso2: string, region: string, regionLabel: string, territory: bool, count: int}>
     */
    private function countriesWithBases(Collection $bases): array
    {
        $countries = [];
        foreach ($this->groupBases($bases, static fn (Base $b): ?string => $b->country_slug) as $group) {
            $first = $group->first();
            if (! $first instanceof Base) {
                continue;
            }
            $country = $first->overseasCountry;
            $region = $first->region;
            $countries[] = [
                'slug' => (string) $first->country_slug,
                'name' => (string) $first->country,
                'iso2' => (string) ($country instanceof OverseasCountry ? $country->iso2 : $first->country_iso2),
                'region' => $region instanceof CombatantCommand ? $region->value : '',
                'regionLabel' => $region instanceof CombatantCommand ? $region->label() : '',
                'territory' => $country instanceof OverseasCountry && $country->is_us_territory,
                'count' => $group->count(),
            ];
        }
        usort($countries, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $countries;
    }

    /**
     * The installation types present, ordered by their raw value — the port of
     * `getBaseTypes()` (src/data/bases/index.ts L208).
     *
     * @param  Collection<int, Base>  $bases
     * @return list<BaseType>
     */
    private function baseTypesPresent(Collection $bases): array
    {
        $types = $bases->map(static fn (Base $b): BaseType => $b->type)->unique()->values()->all();
        usort($types, static fn (BaseType $a, BaseType $b): int => strcmp($a->value, $b->value));

        return $types;
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

        $first = $bases->first();

        if (! $first instanceof Base) {
            return null;
        }

        // Both hubs group by installation type and sort the groups by the type's
        // plural label (NavyBaseState.tsx L60-67 / NavyBasesCountry.tsx L89-96).
        $grouped = $bases->sortBy('name')
            ->groupBy(static fn (Base $b): string => $b->type->pluralLabel())
            ->sortKeys();

        if ($kind === 'state') {
            return response()->view('pages.base-state-hub', [
                'page' => $page,
                'regionName' => (string) $first->state_name,
                'stateAbbr' => (string) $first->state_abbr,
                'baseCount' => $bases->count(),
                'grouped' => $grouped,
            ] + $this->seoVars($page));
        }

        $country = $first->overseasCountry;
        $region = $country instanceof OverseasCountry ? $country->region : $first->region;

        return response()->view('pages.base-country-hub', [
            'page' => $page,
            'regionName' => (string) $first->country,
            'countryIso2' => (string) ($country instanceof OverseasCountry ? $country->iso2 : $first->country_iso2),
            'commandLabel' => $region instanceof CombatantCommand ? $region->label() : '',
            'isUsTerritory' => $country instanceof OverseasCountry && $country->is_us_territory,
            'baseCount' => $bases->count(),
            'countryBases' => $bases,
            'grouped' => $grouped,
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

    /**
     * `/schedule/` and `/map/` — both list every Navy Week host city in tour order.
     */
    private function renderSchedulePage(Page $page, string $view): Response
    {
        return response()->view($view, [
            'page' => $page,
            'events' => $this->navyWeekEvents->all()->sortBy('sequence')->values(),
        ] + $this->seoVars($page));
    }

    /**
     * `/navy-reference/` — the reference library landing page. Card counts are
     * read live from each pillar so they can never drift from the pages they link.
     */
    private function renderNavyReferenceHub(Page $page): Response
    {
        $allBases = $this->bases->all();
        $overseas = $allBases->filter(static fn (Base $b): bool => filled($b->country_slug))->count();
        $ranks = $this->ranks->forCategoryByPaygrade(RankCategory::OfficerCommissioned)->count()
            + $this->ranks->forCategoryByPaygrade(RankCategory::OfficerWarrant)->count()
            + $this->ranks->forCategoryByPaygrade(RankCategory::EnlistedPaygrade)->count();

        $cards = [
            // Badges + descriptions are the legacy NavyReference.tsx `cards` copy verbatim.
            ['badge' => "{$allBases->count()} Installations", 'title' => 'Navy Bases', 'href' => PagePaths::root('bases'), 'description' => 'Naval Stations, Naval Air Stations, Submarine Bases, and Joint Bases across the United States — browse by state or installation type.'],
            ['badge' => "{$overseas} Overseas Bases", 'title' => 'Overseas Bases', 'href' => PagePaths::child('bases', 'overseas'), 'description' => 'Forward-deployed U.S. Navy installations in Japan, Bahrain, Italy, Spain, and more — by host nation and combatant command region.'],
            ['badge' => "{$ranks} Ranks", 'title' => 'Navy Ranks', 'href' => PagePaths::root('ranks'), 'description' => 'Commissioned officers (O-1 to O-10), warrant officers (W-1 to W-5), and enlisted paygrades (E-1 to E-9). Pay, insignia, and history.'],
            ['badge' => $this->ranks->activeRatings()->count().' Ratings', 'title' => 'Navy Ratings', 'href' => PagePaths::root('ratings'), 'description' => "Every active enlisted rating — the Navy's job specialties, from Hospital Corpsman to Boatswain's Mate — grouped by community, plus historic ratings."],
            ['badge' => $this->ranks->designators()->count().' Designators', 'title' => 'Officer Designators', 'href' => PagePaths::root('designators'), 'description' => 'Four-digit codes for every Navy officer community — Unrestricted Line, Restricted Line, and Staff Corps.'],
            ['badge' => 'Veteran Benefits', 'title' => 'VA Disability', 'href' => '/va-disability/', 'description' => 'Plain-language guide to VA disability compensation — pay rates, ratings, common conditions, filing, and TDIU.'],
            ['badge' => 'Veteran Benefits', 'title' => 'Veterans Home Care', 'href' => '/veterans-home-care/', 'description' => 'How the VA pays for in-home care — VA-arranged services vs. the Aid and Attendance pension, 2026 rates, eligibility, and how to apply.'],
            ['badge' => 'Military Observances', 'title' => 'Veterans Day', 'href' => '/veterans-day/', 'description' => 'History and meaning of Veterans Day (Nov 11, 2026), how it differs from Memorial Day, and how the Navy observes it.'],
            ['badge' => $this->pages->allPublishedDiscountBrandPages()->count().' Brands', 'title' => 'Military Discounts', 'href' => PagePaths::root('discounts'), 'description' => 'Verified military, veteran, and first-responder discounts from major brands — eligibility, ID verification, and step-by-step redemption.'],
        ];

        return response()->view('pages.navy-reference-hub', [
            'page' => $page,
            'cards' => $cards,
            'upcoming' => $this->navyWeekEvents->byStatus(NavyWeekStatus::Upcoming)->take(3),
        ] + $this->seoVars($page));
    }

    /**
     * `/navy-designators/` — every officer designator grouped by community.
     *
     * The community cards' one-line taglines are body copy the legacy hub held in
     * the component itself (`src/page-views/NavyDesignatorsHub.tsx`, the
     * `communities` array) — they are presentation strings, not pillar data, so
     * they travel with the view rather than the `ranks` aggregate.
     */
    private function renderDesignatorHub(Page $page): Response
    {
        /** Verbatim from NavyDesignatorsHub.tsx — the `tagline` of each community. */
        $taglines = [
            'url' => 'Warfighters who command and fight ships, submarines, and aircraft.',
            'restricted-line' => 'Specialty line officers in engineering, intelligence, IW, and space.',
            'staff-corps' => 'Professional Staff Corps — medical, legal, supply, civil engineering, chaplain.',
        ];

        $designators = $this->ranks->designators();

        $communities = collect(DesignatorCommunity::cases())
            ->map(fn (DesignatorCommunity $community): array => [
                'label' => $community->label(),
                'href' => PagePaths::child('designators', $community->value),
                'tagline' => $taglines[$community->value],
                'designators' => $designators
                    ->filter(static fn (Rank $r): bool => $r->designator_community === $community)
                    ->values(),
            ])
            ->reject(static fn (array $c): bool => $c['designators']->isEmpty())
            ->values();

        return response()->view('pages.designator-hub', [
            'page' => $page,
            'communities' => $communities,
            // The hub's lede paragraph, verbatim from NavyDesignatorsHub.tsx (it
            // deliberately differs from the page's meta description).
            'intro' => 'Every U.S. Navy officer carries a four-digit designator code that identifies '
                ."their warfare community or staff corps. This reference covers all {$designators->count()} primary "
                .'designators across the three Navy officer communities — Unrestricted Line, Restricted Line, and Staff Corps.',
        ] + $this->seoVars($page));
    }

    /**
     * `/navy-designators/{community}/` — one officer community's designators. The
     * community is carried by the page slug (this hub owns no pageable).
     */
    private function renderDesignatorCommunityHub(Page $page): ?Response
    {
        $community = DesignatorCommunity::tryFrom($page->slug);

        if ($community === null) {
            return null;
        }

        /** Verbatim from `COMMUNITY_DESCRIPTIONS` in src/page-views/NavyDesignatorsCommunity.tsx. */
        $descriptions = [
            'url' => "The Unrestricted Line (URL) communities are the Navy's warfighting officer specialties — "
                .'Surface Warfare, Submarine, Aviation, Special Warfare, and Explosive Ordnance Disposal. '
                .'URL officers are eligible for command at sea.',
            'restricted-line' => 'Restricted Line (RL) officers are line officers with technical or specialty focus — '
                .'engineering, aerospace engineering, intelligence, cryptologic warfare, information professional, '
                .'cyber warfare, public affairs, foreign area, and space cadre. RL officers do not command operational warships.',
            'staff-corps' => 'Staff Corps officers are credentialed professionals — physicians, dentists, nurses, '
                .'attorneys, supply officers, civil engineers, and chaplains — who provide the Navy with specialized '
                .'non-line expertise.',
        ];

        return response()->view('pages.designator-community-hub', [
            'page' => $page,
            'communityLabel' => $community->label(),
            'intro' => $descriptions[$community->value],
            'designators' => $this->ranks->designators()
                ->filter(static fn (Rank $r): bool => $r->designator_community === $community)
                ->values(),
        ] + $this->seoVars($page));
    }

    /**
     * `/navy-designators/{slug}/` — a single officer designator.
     */
    private function renderDesignator(Page $page, Rank $designator): Response
    {
        // The record stores related designators / bases as slug lists; resolve them
        // to their records (dropping unknown slugs) so the cards can show the code,
        // community and name the legacy detail view rendered.
        $bySlug = $this->ranks->designators()->keyBy(static fn (Rank $r): string => $r->slug);

        $relatedDesignators = collect($designator->related_designators ?? [])
            ->map(static fn (mixed $slug): ?Rank => $bySlug->get((string) $slug))
            ->filter()
            ->values();

        $relatedBases = collect($designator->related_base_slugs ?? [])
            ->map(fn (mixed $slug): ?Base => $this->bases->findBySlug((string) $slug))
            ->filter()
            ->values();

        return response()->view('pages.designator', [
            'page' => $page,
            'designator' => $designator,
            'relatedDesignators' => $relatedDesignators,
            'relatedBases' => $relatedBases,
        ] + $this->seoVars($page));
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
            'hubPath' => PagePaths::root('air_shows'),
            'publishedHrefs' => $this->airShowPublishedHrefs(),
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * Every internal href an air-show guide may cross-link. Port of the legacy
     * `isPublishedHref` (src/data/airshows/index.ts): the air-shows hub, the two
     * jet-team headliner hubs, every Fleet Week city guide, and the PUBLISHED air
     * show siblings. Anything else renders as plain text, never a dead link.
     *
     * @return array<int, string>
     */
    private function airShowPublishedHrefs(): array
    {
        $hrefs = $this->airShows->published()
            ->map(fn (AirShow $show): string => PagePaths::child('air_shows', $show->slug))
            ->push(PagePaths::root('air_shows'))
            ->merge($this->fleetWeeks->all()->map(
                fn (FleetWeek $week): string => PagePaths::child('fleet_weeks', $week->slug)
            ))
            ->merge($this->jetTeams->allTeams()->map(
                fn (JetTeam $team): string => rtrim($team->base_path, '/').'/'
            ));

        return $hrefs->values()->all();
    }

    /**
     * The air-show hub (`/air-show/`): the show directory + JSON-LD ItemList.
     *
     * The table lists EVERY show (legacy `airShows`); publication gates only the
     * guide link in the last column. The ItemList, by contrast, is built from the
     * published shows alone (legacy `airShowPublished`).
     */
    private function renderAirShowHub(Page $page, AirShowHubMeta $hub): Response
    {
        $hub->load('faqs');
        $shows = $this->airShows->directory();

        $seo = SeoHead::forPage($page, AirShowPageSchema::buildHub($page, $hub, $this->airShows->published()));

        return response()->view('pages.air-show-hub', [
            'page' => $page,
            'hub' => $hub,
            'shows' => $shows,
            'hubPath' => PagePaths::root('air_shows'),
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

        $team = $city->team;

        // The stops either side of this one come from the canonical season schedule
        // (legacy `getAdjacentStops`), and the sibling is the OTHER team's stop in
        // the same city when both fly the same show (legacy `getSiblingStop`).
        $schedule = $this->jetTeams->schedule($team->team);
        $position = $schedule->search(fn (JetTeamScheduleRow $row): bool => $row->slug === $city->slug);
        $position = is_int($position) ? $position : null;

        $sibling = $this->jetTeams->allTeams()
            ->reject(fn (JetTeam $other): bool => $other->team === $team->team)
            ->map(fn (JetTeam $other): ?array => ($row = $this->jetTeams->schedule($other->team)
                ->first(fn (JetTeamScheduleRow $r): bool => $r->slug === $city->slug)) === null
                    ? null
                    : ['team' => $other, 'row' => $row])
            ->filter()
            ->first();

        $seo = SeoHead::forPage($page, JetTeamPageSchema::buildCity($page, $city, $team));

        return response()->view('pages.jet-team-city', [
            'page' => $page,
            'city' => $city,
            'team' => $team,
            'prevStop' => $position !== null ? $schedule->get($position - 1) : null,
            'nextStop' => $position !== null ? $schedule->get($position + 1) : null,
            'sibling' => $sibling,
            'publishedHrefs' => $this->jetTeamPublishedHrefs(),
            'seoHead' => $seo->render(),
            'noindex' => $seo->isNoindex(),
        ]);
    }

    /**
     * Every internal href the jet-team silo may link to — both team hubs plus each
     * published city guide. Port of the legacy `isPublishedHref` (jetteams/index.ts),
     * which gates cross-links so an unpublished stop renders as plain text instead
     * of a dead link.
     *
     * @return array<int, string>
     */
    private function jetTeamPublishedHrefs(): array
    {
        return $this->jetTeams->allTeams()
            ->flatMap(function (JetTeam $team): Collection {
                $root = rtrim($team->base_path, '/').'/';

                return $this->jetTeams->publishedCities($team->team)
                    ->map(fn (JetTeamCity $city): string => $root.$city->slug.'/')
                    ->prepend($root);
            })
            ->values()
            ->all();
    }

    /**
     * A Navy Week city page (`/city/{slug}/`). Emits Breadcrumb + two
     * GovernmentOrganization nodes + the rich Event (with per-day subEvents) + FAQPage.
     */
    private function renderNavyWeekCity(Page $page, NavyWeekEvent $event): Response
    {
        $event->load(['faqs', 'sources']);

        $seo = SeoHead::forPage($page, NavyWeekCitySchema::build($page, $event));

        // The tour in canonical order — the legacy CityDetail.tsx reads the `events`
        // array positionally for both the prev/next footer and the "more cities" grid.
        $tour = $this->navyWeekEvents->all()->sortBy('sequence')->values();
        $at = $tour->search(static fn (NavyWeekEvent $other): bool => $other->slug === $event->slug);
        $at = is_int($at) ? $at : -1;

        // "Learn more about the U.S. Navy" links to this state's bases hub when the
        // state has any — the port of `getStatesWithBases().find(abbr === stateAbbr)`.
        $stateBases = $this->bases->all()
            ->filter(static fn (Base $b): bool => $b->state_abbr === $event->state_abbr && filled($b->state));
        $stateBase = $stateBases->first();

        return response()->view('pages.navy-week-city', [
            'page' => $page,
            'event' => $event,
            // "More Navy Week cities" — the next three stops still to come.
            'relatedCities' => $tour
                ->reject(static fn (NavyWeekEvent $other): bool => $other->slug === $event->slug)
                ->reject(static fn (NavyWeekEvent $other): bool => $other->status === NavyWeekStatus::Completed)
                ->take(3)
                ->values(),
            'prevCity' => $at > 0 ? $tour->get($at - 1) : null,
            'nextCity' => $at >= 0 ? $tour->get($at + 1) : null,
            'stateWithBases' => $stateBase instanceof Base ? [
                'slug' => (string) $stateBase->state,
                'name' => (string) $stateBase->state_name,
                'count' => $stateBases->count(),
            ] : null,
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

        // "More fleet weeks", ported from getRelatedFleetWeeks() in
        // src/data/fleetweek/index.ts: editor-pinned related_slugs first, then
        // same-season cities, then the rest — and the non-pinned fallbacks are
        // restricted to cities that actually HAVE an official event, so we never
        // auto-suggest a "no fleet week" page. The cards need the city, month and
        // year, so this passes records rather than slugs.
        $others = $this->fleetWeeks->all()
            ->reject(static fn (FleetWeek $other): bool => $other->slug === $week->slug);

        $pinnedSlugs = collect($week->related_slugs ?? [])->map(static fn ($slug): string => (string) $slug);
        $pinned = $pinnedSlugs
            ->map(static fn (string $slug): ?FleetWeek => $others->firstWhere('slug', $slug))
            ->filter()
            ->values();

        $remaining = $others
            ->reject(static fn (FleetWeek $other): bool => $pinnedSlugs->contains($other->slug))
            ->filter(static fn (FleetWeek $other): bool => (bool) $other->has_official_fleet_week);

        $related = $pinned
            ->concat($remaining->where('season', $week->season)->values())
            ->concat($remaining->where('season', '!=', $week->season)->values())
            ->take(4);

        return response()->view('pages.fleet-week', [
            'page' => $page,
            'week' => $week,
            'relatedWeeks' => $related->values(),
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
        // The category's live brand pages, in the repository's curated order (a brand
        // shows only when it has a published page).
        $livePages = new Collection(array_values($this->liveCategoryBrands($category)));
        $brands = collect($this->discountBrandCards($livePages));

        // ItemList entries (absolute URL + display name) for the JSON-LD.
        $brandItems = [];
        foreach ($livePages as $brandPage) {
            $offer = $brandPage->pageable;
            if (! $offer instanceof Offer) {
                continue;
            }

            $audience = (string) $offer->audience_label;
            $brandItems[] = [
                'url' => SeoUrl::absolute((string) $brandPage->url_path),
                'name' => $audience !== ''
                    ? "{$offer->connection->brand} {$audience} Discount"
                    : "{$offer->connection->brand} Military & Veteran Discount",
            ];
        }

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
