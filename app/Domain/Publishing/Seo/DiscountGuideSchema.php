<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Facades\Config;

/**
 * Builds the discount-brand guide's JSON-LD node list, ported 1:1 from the legacy
 * `DiscountDetail.getSeoData()` (`src/page-views/DiscountDetail.tsx`): a Breadcrumb,
 * an Article (authored by T. Madden Alford), a WebSite, the WebPage node
 * (reviewed by Erik Rivera), the author + reviewer Person nodes, and the FAQPage.
 * `SeoHead` prepends the site Organization, so the emitted list matches the legacy
 * `[Organization, …]` order. These are the E-E-A-T authors the legacy pages cite.
 *
 * @phpstan-type Schema array<string, mixed>
 */
final class DiscountGuideSchema
{
    private const AUTHOR = [
        'name' => 'T Madden Alford',
        'url' => '/authors/t-alford/',
        'image' => '/authors/t-alford.jpg',
        'credentials' => "U.S. Naval Academy '02 · U.S. Navy Reserve Captain (O-6) · Former submarine officer, USS Key West",
    ];

    private const REVIEWER = [
        'name' => 'Erik Rivera',
        'url' => '/authors/erik-rivera/',
        'credentials' => "U.S. Naval Academy '04 · Former U.S. Navy Explosive Ordnance Disposal (EOD) officer",
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, Offer $offer): array
    {
        $site = SeoUrl::site();
        $slug = $page->slug;
        $pageUrl = "{$site}/discount/{$slug}/";
        $brand = $offer->connection->brand;
        $audienceLabel = $offer->audience_label;
        $title = (string) $page->title;
        $description = (string) $page->meta_description;
        $ogImage = $page->og_image_path !== null && $page->og_image_path !== ''
            ? $site.$page->og_image_path
            : $site.Config::string('site.default_og_image');
        $datePublished = self::isoDate($page->date_published);
        $dateModified = self::isoDate($page->date_modified);

        $headline = $audienceLabel !== null
            ? "{$brand} {$audienceLabel} Discount (2026)"
            : "{$brand} Military & Veteran Discount (2026)";
        $crumbLabel = $brand.' '.($audienceLabel ?? 'Military').' Discount';

        return [
            self::breadcrumb($site, [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Military Discounts', 'url' => '/discount/'],
                ['name' => $crumbLabel, 'url' => "/discount/{$slug}/"],
            ]),
            [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $headline,
                'description' => $description,
                'url' => $pageUrl,
                'image' => $ogImage,
                'datePublished' => $datePublished,
                'dateModified' => $dateModified,
                'isAccessibleForFree' => true,
                'inLanguage' => 'en-US',
                'author' => ['@id' => "{$site}/authors/t-alford/#person"],
                'publisher' => ['@id' => "{$site}/#organization"],
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $pageUrl],
            ],
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
                '@type' => 'WebPage',
                '@id' => $pageUrl,
                'url' => $pageUrl,
                'name' => $title,
                'isPartOf' => ['@id' => "{$site}/#website"],
                'datePublished' => $datePublished,
                'dateModified' => $dateModified,
                'lastReviewed' => $dateModified,
                'reviewedBy' => ['@id' => "{$pageUrl}#reviewer"],
                'about' => [
                    '@type' => 'Thing',
                    'name' => $audienceLabel !== null
                        ? "{$brand} ".mb_strtolower($audienceLabel).' discount'
                        : "{$brand} military and veteran discount",
                ],
                'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $ogImage],
            ],
            self::authorPerson($site),
            [
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                '@id' => "{$pageUrl}#reviewer",
                'name' => self::REVIEWER['name'],
                'description' => self::REVIEWER['credentials'],
                'url' => $site.self::REVIEWER['url'],
            ],
            self::faqPage($offer),
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    private static function breadcrumb(string $site, array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (array $item, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['name'],
                    'item' => SeoUrl::absolute($item['url']),
                ],
                $items,
                array_keys($items),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function authorPerson(string $site): array
    {
        $url = $site.self::AUTHOR['url'];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => "{$url}#person",
            'name' => self::AUTHOR['name'],
            'url' => $url,
            'image' => $site.self::AUTHOR['image'],
            'jobTitle' => 'Editor, NavyWeek.org',
            'description' => self::AUTHOR['credentials'],
            'knowsAbout' => [
                'military discounts',
                'veteran benefits',
                'U.S. Navy',
                'ID.me verification',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function faqPage(Offer $offer): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $offer->faqs->map(static fn ($faq): array => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->answer],
            ])->all(),
        ];
    }

    private static function isoDate(mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : '';
    }
}
