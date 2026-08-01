<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Facades\Config;

/**
 * JSON-LD for a local-discount rollup hub — the `/discounts/` root, a `/discounts/{state}/`
 * state index, and a `/discounts/{state}/{city}/` city index. Ported from the legacy
 * local-hub graph: `SeoHead` prepends Organization, then
 *
 *   BreadcrumbList → Article → WebSite → ItemList
 *
 * (no FAQPage, no LocalBusiness — those belong to the detail page). Parameterized by the
 * controller, which supplies the level's headline, breadcrumb chain, and ItemList entries.
 * Ordering follows the repository rollup (state-name / city-name / company) — an accepted
 * deviation from any legacy curated order, shared with the other hub ItemLists.
 */
final class LocalDiscountHubSchema
{
    use BuildsSeoSchema;

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @param  list<array{url: string, name: string}>  $items
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, string $headline, array $crumbs, array $items): array
    {
        $site = SeoUrl::site();
        $path = $page->url_path;

        $itemList = [];
        $position = 0;
        foreach ($items as $item) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'url' => SeoUrl::absolute($item['url']),
                'name' => $item['name'],
            ];
        }

        return [
            self::breadcrumb($crumbs),
            self::article(
                headline: $headline,
                description: (string) $page->meta_description,
                path: $path,
                imagePath: $page->og_image_path,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
            ),
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                '@id' => "{$site}/#website",
                'name' => Config::string('site.name'),
                'url' => $site,
                'publisher' => ['@id' => "{$site}/#organization"],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $headline,
                'url' => SeoUrl::absolute($path),
                'numberOfItems' => count($itemList),
                'itemListElement' => $itemList,
            ],
        ];
    }
}
