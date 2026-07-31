<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Models\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * JSON-LD for the `/discount/` directory landing page, a 1:1 port of `DiscountHub`
 * getSeoData + `buildDiscountsItemListSchema` (`src/data/discounts/seo.ts`). Emitted
 * node list (after `SeoHead` prepends Organization):
 *
 *   BreadcrumbList → Article → ItemList → FAQPage
 *
 * The Article is org-authored (no Person byline) and carries a DISTINCT hardcoded
 * description (≠ the page meta description). The ItemList links every published
 * discount-brand page; the hub FAQs live on the page's polymorphic `faqs` (the legacy
 * HUB_FAQS, seeded at generation). Ordering follows the repository's `url_path` order
 * (accepted deviation from the legacy curated registry order — same shared follow-up
 * as the ranks/fleet-week hub ItemLists).
 */
final class DiscountIndexSchema
{
    use BuildsSeoSchema;

    /** The Article description — a distinct hardcoded string, separate from the meta description. */
    private const ARTICLE_DESCRIPTION = 'A directory of verified military and veteran discounts from major brands, with eligibility, verification, redemption steps, and exclusions for each company.';

    /**
     * @param  Collection<int, Page>  $brandPages  Published discount-brand pages (Offer + connection loaded).
     * @param  iterable<object{question: string, answer: string}>  $hubFaqs
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, Collection $brandPages, iterable $hubFaqs): array
    {
        $items = [];
        $position = 0;
        foreach ($brandPages as $brandPage) {
            $offer = $brandPage->pageable;
            if (! $offer instanceof Offer) {
                continue;
            }
            $brand = $offer->connection->brand;
            $audience = $offer->audience_label;
            $items[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'url' => SeoUrl::absolute($brandPage->url_path),
                'name' => $audience !== null && $audience !== ''
                    ? "{$brand} {$audience} Discount"
                    : "{$brand} Military & Veteran Discount",
            ];
        }

        return [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Military Discounts', 'url' => '/discount/'],
            ]),
            self::article(
                headline: 'Military & Veteran Discounts Directory',
                description: self::ARTICLE_DESCRIPTION,
                path: '/discount/',
                imagePath: $page->og_image_path,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
            ),
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'Military & Veteran Discounts Directory',
                'url' => SeoUrl::absolute('/discount/'),
                'numberOfItems' => count($items),
                'itemListElement' => $items,
            ],
            self::faqPageFrom($hubFaqs),
        ];
    }
}
