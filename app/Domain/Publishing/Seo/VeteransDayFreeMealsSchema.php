<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Publishing\Models\Page;
use App\Models\User;

/**
 * JSON-LD for the `/veterans-day/free-meals/` roundup — a 1:1 port of the inline
 * graph assembled in the legacy `src/page-views/VeteransDayFreeMeals.tsx`. Emitted
 * node list (after `SeoHead` prepends Organization):
 *
 *   BreadcrumbList → Article → Person (author) → ItemList → FAQPage
 *
 * The Article is authored by the page's byline Person (t-alford); its dateModified
 * tracks the freshest meal verification (computed by the caller). The ItemList links
 * each verified meal (its discount page when one exists, else the primary source).
 * Crumbs + ItemList entries are built by the caller (so no route literal is spelled
 * here); FAQs are computed from the live stats and passed in.
 */
final class VeteransDayFreeMealsSchema
{
    use BuildsSeoSchema;

    private const ARTICLE_HEADLINE = 'Veterans Day Free Meals 2026: Verified Restaurant Offers';

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @param  list<array{name: string, url: string}>  $itemListEntries  Absolute-url ListItem sources, in render order.
     * @param  iterable<object{question: string, answer: string}>  $faqs
     * @return list<array<string, mixed>>
     */
    public static function build(
        Page $page,
        array $crumbs,
        string $description,
        array $itemListEntries,
        string $dateModified,
        iterable $faqs,
    ): array {
        $site = SeoUrl::site();
        $author = $page->author;
        $authorProfile = self::authorProfileUrl($author);

        $items = [];
        $position = 0;
        foreach ($itemListEntries as $entry) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $entry['name'],
                'url' => $entry['url'],
            ];
        }

        $graph = [
            self::breadcrumb($crumbs),
            self::article(
                headline: self::ARTICLE_HEADLINE,
                description: $description,
                path: $page->url_path,
                imagePath: $page->og_image_path,
                datePublished: self::isoDate($page->date_published),
                dateModified: $dateModified,
                author: $authorProfile !== null ? ['@id' => $authorProfile.'#person'] : null,
            ),
        ];

        // The author Person node (only when the page carries a byline with a profile slug).
        if ($author instanceof User && $authorProfile !== null) {
            $graph[] = self::authorPerson($site, $author, $authorProfile);
        }

        $graph[] = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Veterans Day Free Meals 2026',
            'description' => 'Verified Veterans Day 2026 free-meal offers from national restaurant chains.',
            'numberOfItems' => count($items),
            'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            'itemListElement' => $items,
        ];

        $graph[] = self::faqPageFrom($faqs);

        return $graph;
    }
}
