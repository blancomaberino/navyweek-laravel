<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Models\LocalStore;
use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Facades\Config;

/**
 * JSON-LD node list for a local-business discount detail page
 * (`/discounts/{state}/{city}/{business}/`), ported from the legacy local-discount
 * detail view. Same E-E-A-T graph as the national discount guide — Breadcrumb, Article,
 * WebSite, WebPage, author + reviewer Person, FAQPage — with a **LocalBusiness** node
 * (address + geo + opening hours from the primary store) inserted after WebPage.
 * `SeoHead` prepends the site Organization, so the emitted order matches the legacy
 * `[Organization, …]` graph.
 */
final class LocalDiscountSchema
{
    use BuildsSeoSchema;

    /**
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, LocalDiscount $ld): array
    {
        $site = SeoUrl::site();
        $path = "/discounts/{$ld->state}/{$ld->city}/{$ld->business_slug}/";
        $pageUrl = $site.$path;
        $title = (string) $page->title;
        $description = (string) $page->meta_description;
        $ogImage = self::ogImage($page);
        $datePublished = self::isoDate($page->date_published);
        $dateModified = self::isoDate($page->date_modified);

        $author = $page->author;
        $reviewer = $page->reviewer;
        $authorProfileUrl = self::authorProfileUrl($author);
        $reviewerPersonId = $reviewer !== null ? "{$pageUrl}#reviewer" : null;

        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => "{$ld->company} Military & Veteran Discount (2026)",
            'description' => $description,
            'url' => $pageUrl,
            'image' => $ogImage,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'isAccessibleForFree' => true,
            'inLanguage' => 'en-US',
            'publisher' => ['@id' => "{$site}/#organization"],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $pageUrl],
        ];
        if ($authorProfileUrl !== null) {
            $article['author'] = ['@id' => $authorProfileUrl.'#person'];
        }

        $webPage = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $pageUrl,
            'url' => $pageUrl,
            'name' => $title,
            'isPartOf' => ['@id' => "{$site}/#website"],
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'about' => ['@type' => 'Thing', 'name' => "{$ld->company} military and veteran discount"],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $ogImage],
        ];
        if ($reviewerPersonId !== null) {
            $webPage['lastReviewed'] = $dateModified;
            $webPage['reviewedBy'] = ['@id' => $reviewerPersonId];
        }

        // Order: Breadcrumb, Article, WebSite, WebPage, LocalBusiness, author Person,
        // reviewer Person, FAQPage.
        $nodes = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => $ld->state_name, 'url' => "/discounts/{$ld->state}/"],
                ['name' => $ld->city_name, 'url' => "/discounts/{$ld->state}/{$ld->city}/"],
                ['name' => $ld->company, 'url' => $path],
            ]),
            $article,
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                '@id' => "{$site}/#website",
                'name' => Config::string('site.name'),
                'url' => $site,
                'publisher' => ['@id' => "{$site}/#organization"],
            ],
            $webPage,
            self::localBusiness($pageUrl, $ld),
        ];
        if ($author !== null && $authorProfileUrl !== null) {
            $nodes[] = self::authorPerson($site, $author, $authorProfileUrl);
        }
        if ($reviewer !== null) {
            $nodes[] = self::reviewerPerson($reviewer, $reviewerPersonId);
        }
        $nodes[] = self::faqPageFrom($ld->faqs);

        return $nodes;
    }

    /**
     * The LocalBusiness node — identity + the primary store's postal address, geo
     * coordinates, phone, and opening-hours specification. The primary store is the
     * lowest `sort_order` (the store the legacy detail page's NAP block used).
     *
     * @return array<string, mixed>
     */
    private static function localBusiness(string $pageUrl, LocalDiscount $ld): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => "{$pageUrl}#localbusiness",
            'name' => $ld->company,
            'url' => $ld->official_url,
        ];
        if ($ld->business_type !== '') {
            $node['additionalType'] = 'https://schema.org/'.$ld->business_type;
        }
        if ($ld->price_range !== null && $ld->price_range !== '') {
            $node['priceRange'] = $ld->price_range;
        }
        if ($ld->service_area !== null && $ld->service_area !== '') {
            $node['areaServed'] = $ld->service_area;
        }

        $store = $ld->stores->sortBy('sort_order')->first();
        if ($store instanceof LocalStore) {
            $node['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $store->street,
                'addressLocality' => $store->city,
                'addressRegion' => $store->state_abbr,
                'postalCode' => $store->zip,
                'addressCountry' => 'US',
            ];
            $node['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $store->lat,
                'longitude' => (float) $store->lng,
            ];
            if ($store->phone !== null && $store->phone !== '') {
                $node['telephone'] = $store->phone;
            }
            $hours = self::openingHours($store);
            if ($hours !== []) {
                $node['openingHoursSpecification'] = $hours;
            }
        }

        return $node;
    }

    /**
     * OpeningHoursSpecification[] from the store's hours rows (day_of_week/opens/closes).
     *
     * @return list<array<string, mixed>>
     */
    private static function openingHours(LocalStore $store): array
    {
        $specs = [];
        foreach ($store->hours as $row) {
            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $row->day_of_week,
                'opens' => $row->opens,
                'closes' => $row->closes,
            ];
        }

        return $specs;
    }
}
