<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Controllers;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
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
        private readonly DiscountCategoryRepositoryInterface $categories,
    ) {}

    public function show(Request $request): Response
    {
        // Look up by the exact path the middleware already canonicalized and
        // validated — re-normalizing here (lowercasing/slash-collapsing) would use
        // a different key than the middleware's existence check and could serve a
        // page for a non-canonical URL the middleware meant to 301.
        $page = Page::query()
            ->where('is_published', true)
            ->where('url_path', $request->getPathInfo())
            ->first();

        if ($page === null) {
            abort(404);
        }

        if ($page->page_type === PageType::DiscountBrand && $page->pageable instanceof Offer) {
            return $this->renderDiscountGuide($page, $page->pageable);
        }

        if ($page->page_type === PageType::DiscountCategoryHub && $page->pageable instanceof DiscountCategory) {
            return $this->renderDiscountCategory($page, $page->pageable);
        }

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
     * The category hub (`/discount/{slug}/`): the ordered brand grid for one
     * category. `orderedConnections` gives the legacy sort; a brand is shown only
     * when it has a published discount-brand page (the card links straight to it).
     */
    private function renderDiscountCategory(Page $page, DiscountCategory $category): Response
    {
        $ordered = $this->categories->orderedConnections($category);

        // The live discount-brand pages for this category's connections, in one
        // query scoped by a subquery on the relevant offers (no morph closure).
        $brandPages = Page::query()
            ->where('page_type', PageType::DiscountBrand)
            ->where('is_published', true)
            ->where('pageable_type', (new Offer)->getMorphClass())
            ->whereIn('pageable_id', Offer::query()->whereIn('connection_id', $ordered->pluck('id'))->select('id'))
            ->with('pageable')
            ->get();

        /** @var array<int, array{url: string, audience: string|null}> $liveByConnectionId */
        $liveByConnectionId = [];
        foreach ($brandPages as $brandPage) {
            $offer = $brandPage->pageable;
            if ($offer instanceof Offer) {
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
