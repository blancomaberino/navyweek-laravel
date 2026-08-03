<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Support\PagePaths;

/**
 * JSON-LD for a discount CATEGORY hub (`/discount/{slug}/`), a 1:1 port of the legacy
 * `DiscountCategory.getSeoData`: Breadcrumb + Article + ItemList (no WebSite, no
 * FAQPage — that is the brand-guide graph). `SeoHead` prepends the site Organization,
 * so the emitted list matches the legacy `[Organization, …]` order. Hub Articles are
 * authored by the Organization (no Person byline).
 *
 * @phpstan-type BrandItem array{url: string, name: string}
 */
final class DiscountCategorySchema
{
    use BuildsSeoSchema;

    /**
     * @param  list<BrandItem>  $brandItems  Ordered live brands (absolute URL + ItemList name).
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, DiscountCategory $category, array $brandItems): array
    {
        // The category's own canonical URL comes from its stored url_path (follows an
        // editor rename / family-prefix change); ancestors derive from the family root.
        $pageUrl = SeoUrl::absolute($page->url_path);

        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Military Discounts', 'url' => PagePaths::root('discounts')],
            ['name' => $category->name, 'url' => $page->url_path],
        ];

        return [
            self::breadcrumb($crumbs),
            // The generic org-authored Article (no Person byline) shared by every
            // pillar/hub page — see BuildsSeoSchema::article().
            self::article(
                headline: $category->h1,
                description: $category->meta_description,
                path: $page->url_path,
                imagePath: $page->og_image_path,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
            ),
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $category->name,
                'url' => $pageUrl,
                'numberOfItems' => count($brandItems),
                'itemListElement' => array_map(
                    static fn (array $item, int $i): array => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'url' => $item['url'],
                        'name' => $item['name'],
                    ],
                    $brandItems,
                    array_keys($brandItems),
                ),
            ],
        ];
    }
}
