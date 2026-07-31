<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Seo;

use App\Domain\Pillars\Models\Rank;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Seo\BuildsSeoSchema;
use App\Domain\Publishing\Seo\SeoUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * JSON-LD for the two consolidated reference-list pages — `/navy-ranks/` and
 * `/navy-ratings/` — a 1:1 port of `NavyRanksHub`/`NavyRatingsHub` `getSeoData` +
 * `src/data/ranks/seo.ts`. Emitted node list (after `SeoHead` prepends Organization):
 *
 *   Organization → BreadcrumbList → Article → ItemList(×2)
 *
 * The Article is the generic org-authored one shared with the other pillars. Each
 * ItemList's `ListItem.url` is the entry's in-page anchor (`{listPath}#{slug}`), and
 * its name interpolates the paygrade, exactly as `buildRanksItemListSchema` does. The
 * builder is parameterized over the (name → ordered entries) groups so both pages
 * share it.
 *
 * Ordering note: the officer + enlisted ItemLists are byte-identical to the legacy
 * (numeric paygrade ascending). The two RATINGS ItemLists use a deterministic order
 * (active = alphabetical by name; historic = decommissioned-year desc) that differs
 * from the legacy curated module-array order — the `ranks` table has no `sort_order`
 * column, so that hand-curated order isn't recoverable from the DB. This is an
 * accepted deviation (the item set, count, names, and anchor URLs all match); exact
 * ordering parity would need an import-populated sort column, tracked as a follow-up.
 */
final class RankListSchema
{
    use BuildsSeoSchema;

    /**
     * @param  string  $listPath  The page path with leading+trailing slash (e.g. "/navy-ranks/").
     * @param  string  $crumbLabel  The final breadcrumb label ("Navy Ranks" / "Navy Ratings").
     * @param  array<string, Collection<int, Rank>>  $itemLists  Ordered ItemList name → entries.
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, string $listPath, string $crumbLabel, array $itemLists): array
    {
        $listUrl = SeoUrl::absolute($listPath);

        $nodes = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => $crumbLabel, 'url' => $listPath],
            ]),
            self::article(
                headline: self::headline($page),
                description: (string) $page->meta_description,
                path: $listPath,
                imagePath: $page->og_image_path,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
            ),
        ];

        foreach ($itemLists as $name => $entries) {
            $nodes[] = self::itemList($name, $listUrl, $entries);
        }

        return $nodes;
    }

    /**
     * A schema.org ItemList of ranks/ratings — each ListItem links to the entry's
     * in-page anchor and is named `"{name} ({paygrade})"` (port of
     * `buildRanksItemListSchema`).
     *
     * @param  Collection<int, Rank>  $entries
     * @return array<string, mixed>
     */
    private static function itemList(string $name, string $listUrl, Collection $entries): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'url' => $listUrl,
            'numberOfItems' => $entries->count(),
            'itemListElement' => $entries->values()->map(static fn (Rank $rank, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => "{$listUrl}#{$rank->slug}",
                'name' => "{$rank->name} ({$rank->paygrade})",
            ])->all(),
        ];
    }

    /**
     * The Article headline — the page title without the " | {site name}" suffix, so it
     * matches the legacy hubs' headline (which drop the site suffix the `<title>` keeps).
     */
    private static function headline(Page $page): string
    {
        $title = (string) $page->title;
        $suffix = ' | '.Config::string('site.name');

        return str_ends_with($title, $suffix) ? substr($title, 0, -strlen($suffix)) : $title;
    }
}
