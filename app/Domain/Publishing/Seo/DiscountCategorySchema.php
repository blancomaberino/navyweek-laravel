<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Publishing\Models\Page;

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
        $site = SeoUrl::site();
        $slug = $page->slug;
        $pageUrl = "{$site}/discount/{$slug}/";
        $orgId = "{$site}/#organization";
        $ogImage = self::ogImage($site, $page);
        $datePublished = self::isoDate($page->date_published);
        $dateModified = self::isoDate($page->date_modified);

        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Military Discounts', 'url' => '/discount/'],
            ['name' => $category->name, 'url' => "/discount/{$slug}/"],
        ];

        return [
            self::breadcrumb($crumbs),
            [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $category->h1,
                'description' => $category->meta_description,
                'url' => $pageUrl,
                'image' => $ogImage,
                'datePublished' => $datePublished,
                'dateModified' => $dateModified,
                'isAccessibleForFree' => true,
                'inLanguage' => 'en-US',
                'author' => ['@id' => $orgId],
                'publisher' => ['@id' => $orgId],
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $pageUrl],
            ],
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
