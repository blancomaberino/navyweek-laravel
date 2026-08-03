<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Http\Controllers;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Crm\Models\Connection;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Seo\BasePageSchema;
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
