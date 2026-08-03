<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Seo;

use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Seo\BuildsSeoSchema;
use App\Domain\Publishing\Seo\SeoUrl;
use App\Domain\Publishing\Support\PagePaths;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * JSON-LD for the fleet-week pages, a 1:1 port of `FleetWeekDetail.getSeoData` +
 * `FleetWeekHub` + `src/data/fleetweek/seo.ts`.
 *
 * Detail (`/fleetweek/{slug}/`), after `SeoHead` prepends Organization:
 *   BreadcrumbList → Article → WebPage → Person(author) → Person(reviewer)
 *   → FAQPage → Festival?
 * The Festival node is emitted only when the record has a `festival` block (Tier-3
 * cities without an official event omit it). Author/reviewer Person nodes are driven
 * by the page byline (seeded t-alford/erik-rivera = legacy TRUST_AUTHOR/DEFAULT_REVIEWER);
 * the author `knowsAbout` is the fleet-week-specific list.
 *
 * Hub (`/fleetweek/`): BreadcrumbList → Article → ItemList → FAQPage (the hub FAQs are
 * seeded onto the hub page's polymorphic `faqs` — the legacy HUB_FAQS constant).
 */
final class FleetWeekPageSchema
{
    use BuildsSeoSchema;

    /**
     * The hub Article description — a DISTINCT hardcoded string in the legacy hub
     * (FleetWeekHub.tsx), separate from the page meta description. Byte-locked.
     */
    private const HUB_ARTICLE_DESCRIPTION = 'A directory of U.S. fleet weeks with dates, air shows, Parade of Ships, free ship tours, and the best free viewing spots for each host city.';

    /**
     * @return list<array<string, mixed>>
     */
    public static function buildDetail(Page $page, FleetWeek $week): array
    {
        $site = SeoUrl::site();
        $path = $page->url_path;
        $url = "{$site}{$path}";
        $author = $page->author;
        $reviewer = $page->reviewer;

        $nodes = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Fleet Week', 'url' => PagePaths::root('fleet_weeks')],
                ['name' => "{$week->city} Fleet Week", 'url' => $path],
            ]),
            self::article(
                headline: "{$week->branding_name} {$week->year}",
                description: $week->meta_description,
                path: $path,
                imagePath: $week->og_image,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
                author: $author !== null ? ['@id' => "{$site}/authors/{$author->slug}/#person"] : null,
            ),
            self::webPage($site, $url, $week, $page, $reviewer !== null),
        ];

        if ($author !== null) {
            $nodes[] = self::authorPerson($site, $author, $week);
        }
        if ($reviewer !== null) {
            $nodes[] = self::reviewerPerson($site, $url, $reviewer);
        }

        $nodes[] = self::faqPageFrom($week->faqs);

        if (is_array($week->festival) && $week->festival !== []) {
            $nodes[] = self::festivalNode($url, $site.$week->og_image, $week->festival);
        }

        return $nodes;
    }

    /**
     * @param  Collection<int, FleetWeek>  $weeks  All cities, in list order.
     * @param  iterable<object{question: string, answer: string}>  $hubFaqs  Hub FAQs (seeded on the hub page).
     * @return list<array<string, mixed>>
     */
    public static function buildHub(Page $page, Collection $weeks, iterable $hubFaqs): array
    {
        $listUrl = SeoUrl::absolute(PagePaths::root('fleet_weeks'));

        return [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Fleet Week', 'url' => PagePaths::root('fleet_weeks')],
            ]),
            self::article(
                headline: 'U.S. Fleet Week Guide, City by City',
                description: self::HUB_ARTICLE_DESCRIPTION,
                path: $page->url_path,
                imagePath: $page->og_image_path,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
            ),
            // NOTE: the ItemList element order follows the repository's `all()` order
            // (alphabetical by city), which differs from the legacy curated registry
            // order — an accepted deviation, since the `fleet_weeks` table has no
            // display-order column (item set, names, and URLs match; only position
            // differs). Exact ordering parity needs an import-populated sort column
            // (a shared follow-up with the /navy-ratings/ ItemList).
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'U.S. Fleet Week Guide, City by City',
                'url' => $listUrl,
                'numberOfItems' => $weeks->count(),
                'itemListElement' => $weeks->values()->map(static fn (FleetWeek $c, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => SeoUrl::absolute(PagePaths::child('fleet_weeks', $c->slug)),
                    'name' => "{$c->branding_name} {$c->year}",
                ])->all(),
            ],
            self::faqPageFrom($hubFaqs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function webPage(string $site, string $url, FleetWeek $week, Page $page, bool $hasReviewer): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => "{$url}#webpage",
            'url' => $url,
            'name' => $week->meta_title,
            'isPartOf' => ['@id' => "{$site}/#website"],
            'datePublished' => self::isoDate($page->date_published),
            'dateModified' => self::isoDate($page->date_modified),
        ];
        if ($hasReviewer) {
            $node['lastReviewed'] = self::isoDate($page->date_modified);
            $node['reviewedBy'] = ['@id' => "{$url}#reviewer"];
        }
        $node['about'] = ['@type' => 'Thing', 'name' => "{$week->branding_name} {$week->year}"];
        $node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $site.$week->og_image];

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private static function authorPerson(string $site, User $author, FleetWeek $week): array
    {
        $profileUrl = "{$site}/authors/{$author->slug}/";

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => "{$profileUrl}#person",
            'name' => $author->name,
            'url' => $profileUrl,
        ];
        if ($author->avatar_path !== null && $author->avatar_path !== '') {
            $node['image'] = $site.$author->avatar_path;
        }
        if ($author->job_title !== null && $author->job_title !== '') {
            $node['jobTitle'] = $author->job_title;
        }
        if ($author->credentials !== null && $author->credentials !== '') {
            $node['description'] = $author->credentials;
        }
        $node['knowsAbout'] = ['Fleet Week', 'U.S. Navy', 'Blue Angels', "{$week->city} Fleet Week", 'Navy ship tours'];

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private static function reviewerPerson(string $site, string $url, User $reviewer): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            '@id' => "{$url}#reviewer",
            'name' => $reviewer->name,
            'description' => (string) $reviewer->credentials,
            'url' => "{$site}/authors/{$reviewer->slug}/",
        ];
    }

    /**
     * The Festival node (`buildFestivalSchema`) — the city's official event, organized
     * by the local association (never NavyWeek). Optional streetAddress/postalCode/geo,
     * previousStartDate, and performers are emitted only when present.
     *
     * @param  array<string, mixed>  $festival
     * @return array<string, mixed>
     */
    private static function festivalNode(string $url, string $image, array $festival): array
    {
        /** @var array<string, mixed> $loc */
        $loc = is_array($festival['location'] ?? null) ? $festival['location'] : [];

        $address = ['@type' => 'PostalAddress'];
        if (! empty($loc['streetAddress'])) {
            $address['streetAddress'] = $loc['streetAddress'];
        }
        $address['addressLocality'] = $loc['locality'] ?? '';
        $address['addressRegion'] = $loc['region'] ?? '';
        if (! empty($loc['postalCode'])) {
            $address['postalCode'] = $loc['postalCode'];
        }
        $address['addressCountry'] = 'US';

        $place = ['@type' => 'Place', 'name' => $loc['name'] ?? '', 'address' => $address];
        if (is_numeric($loc['lat'] ?? null) && is_numeric($loc['lng'] ?? null)) {
            $place['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $loc['lat'], 'longitude' => $loc['lng']];
        }

        $eventStatus = is_string($festival['eventStatus'] ?? null) ? $festival['eventStatus'] : 'EventScheduled';

        /** @var array<string, mixed> $organizer */
        $organizer = is_array($festival['organizer'] ?? null) ? $festival['organizer'] : [];
        /** @var list<array<string, mixed>> $performers */
        $performers = is_array($festival['performers'] ?? null) ? array_values($festival['performers']) : [];

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Festival',
            'name' => $festival['name'] ?? '',
            'description' => $festival['description'] ?? '',
            'startDate' => $festival['startDate'] ?? '',
            'endDate' => $festival['endDate'] ?? '',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => "https://schema.org/{$eventStatus}",
        ];
        if (! empty($festival['previousStartDate'])) {
            $node['previousStartDate'] = $festival['previousStartDate'];
        }
        $node['location'] = $place;
        $node['organizer'] = [
            '@type' => 'Organization',
            'name' => $organizer['name'] ?? '',
            'url' => $organizer['url'] ?? '',
        ];
        if ($performers !== []) {
            $node['performer'] = array_map(static fn (array $p): array => [
                '@type' => 'PerformingGroup',
                'name' => $p['name'] ?? '',
            ], $performers);
        }
        $node['image'] = $image;
        $node['url'] = $url;
        // Legacy never maps this field, so a fleet-week Festival is always free-to-attend.
        $node['isAccessibleForFree'] = true;

        return $node;
    }
}
