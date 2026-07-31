<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Controllers;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Crm\Models\Connection;
use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Models\Rank;
use App\Domain\Pillars\Repositories\AirShowRepositoryInterface;
use App\Domain\Pillars\Repositories\FleetWeekRepositoryInterface;
use App\Domain\Pillars\Repositories\RankRepositoryInterface;
use App\Domain\Pillars\Seo\AirShowPageSchema;
use App\Domain\Pillars\Seo\BasePageSchema;
use App\Domain\Pillars\Seo\FleetWeekPageSchema;
use App\Domain\Pillars\Seo\RankListSchema;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Seo\DiscountCategorySchema;
use App\Domain\Publishing\Seo\DiscountGuideSchema;
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
            default => null,
        };
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
