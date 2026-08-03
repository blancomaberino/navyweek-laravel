<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Seo;

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\AirShowStatus;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Seo\BuildsSeoSchema;
use App\Domain\Publishing\Seo\SeoUrl;
use App\Domain\Publishing\Support\PagePaths;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * JSON-LD for the air-show pages, a 1:1 port of `AirShowDetail.getSeoData` +
 * `AirShowHub` + `src/data/airshows/seo.ts`.
 *
 * Detail (`/air-show/{slug}/`), after `SeoHead` prepends Organization:
 *   BreadcrumbList → Article → WebPage → Person(author) → Person(reviewer)
 *   → FAQPage → Event?
 * The Event node is emitted only when {@see AirShow::emitsEventSchema()} (published,
 * confirmed date, no canonical override). Author/reviewer Person nodes are built
 * from the page byline (the seeded t-alford/erik-rivera editorial users, whose
 * fields equal the legacy TRUST_AUTHOR/DEFAULT_REVIEWER constants); the author's
 * `knowsAbout` is the air-show-specific list from the legacy getSeoData.
 *
 * Hub (`/air-show/`): BreadcrumbList → Article → ItemList → FAQPage.
 */
final class AirShowPageSchema
{
    use BuildsSeoSchema;

    /**
     * @return list<array<string, mixed>>
     */
    public static function buildDetail(Page $page, AirShow $show): array
    {
        $site = SeoUrl::site();
        $path = $page->url_path;
        $url = "{$site}{$path}";
        $author = $page->author;
        $reviewer = $page->reviewer;

        $nodes = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Air Shows', 'url' => PagePaths::root('air_shows')],
                ['name' => $show->short_name, 'url' => $path],
            ]),
            self::article(
                headline: $show->h1,
                description: $show->meta_description,
                path: $path,
                imagePath: $show->og_image,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
                author: $author !== null ? ['@id' => "{$site}/authors/{$author->slug}/#person"] : null,
            ),
            self::webPage($site, $url, $show, $page, $reviewer !== null),
        ];

        if ($author !== null) {
            $nodes[] = self::authorPerson($site, $author, $show);
        }
        if ($reviewer !== null) {
            $nodes[] = self::reviewerPerson($site, $url, $reviewer);
        }

        $nodes[] = self::faqPageFrom($show->faqs);

        if ($show->emitsEventSchema()) {
            $nodes[] = self::eventNode($site, $url, $show);
        }

        return $nodes;
    }

    /**
     * @param  Collection<int, AirShow>  $shows  Published shows, in list order.
     * @return list<array<string, mixed>>
     */
    public static function buildHub(Page $page, AirShowHubMeta $hub, Collection $shows): array
    {
        $listUrl = SeoUrl::absolute(PagePaths::root('air_shows'));

        return [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Air Shows', 'url' => PagePaths::root('air_shows')],
            ]),
            self::article(
                headline: $hub->seo_headline,
                description: $hub->meta_description,
                path: $page->url_path,
                imagePath: $hub->og_image,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
            ),
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $hub->seo_headline,
                'url' => $listUrl,
                'numberOfItems' => $shows->count(),
                'itemListElement' => $shows->values()->map(static fn (AirShow $s, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => SeoUrl::absolute(PagePaths::child('air_shows', $s->slug)),
                    'name' => "{$s->name} {$s->year}",
                ])->all(),
            ],
            self::faqPageFrom($hub->faqs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function webPage(string $site, string $url, AirShow $show, Page $page, bool $hasReviewer): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => "{$url}#webpage",
            'url' => $url,
            'name' => $show->meta_title,
            'isPartOf' => ['@id' => "{$site}/#website"],
            'datePublished' => self::isoDate($page->date_published),
            'dateModified' => self::isoDate($page->date_modified),
        ];
        // lastReviewed / reviewedBy only when the #reviewer Person node exists, so the
        // @id never dangles (the byline is always seeded, so this is the normal path).
        if ($hasReviewer) {
            $node['lastReviewed'] = self::isoDate($page->date_modified);
            $node['reviewedBy'] = ['@id' => "{$url}#reviewer"];
        }
        $node['about'] = ['@type' => 'Thing', 'name' => "{$show->name} {$show->year}"];
        $node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $site.$show->og_image];

        return $node;
    }

    /**
     * The author Person — the assigned byline user (t-alford), with the air-show
     * `knowsAbout` list. Optional user fields are emitted only when populated, and in
     * the legacy `buildPersonSchema` key order (image → jobTitle → description → knowsAbout).
     *
     * @return array<string, mixed>
     */
    private static function authorPerson(string $site, User $author, AirShow $show): array
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
        $node['knowsAbout'] = ['military air shows', "{$show->city} air show", $show->name, $show->headliner];

        return $node;
    }

    /**
     * The reviewer Person — keyed per-page (`{pageUrl}#reviewer`), name + credentials +
     * profile link, matching the legacy inline reviewer literal.
     *
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
     * The Event node (`buildAirShowEventSchema`) — the show as a real-world event, with
     * the organizer being the show itself (never NavyWeek). `streetAddress`/`postalCode`
     * are added only when present, matching the legacy conditional.
     *
     * @return array<string, mixed>
     */
    private static function eventNode(string $site, string $url, AirShow $show): array
    {
        /** @var array<string, mixed> $loc */
        $loc = $show->location ?? [];
        /** @var array<string, mixed> $offer */
        $offer = $show->offer ?? [];
        /** @var array<string, mixed> $organizer */
        $organizer = $show->organizer ?? [];

        $address = [
            '@type' => 'PostalAddress',
            'addressLocality' => $loc['addressLocality'] ?? '',
            'addressRegion' => $loc['addressRegion'] ?? '',
            'addressCountry' => $loc['addressCountry'] ?? '',
        ];
        if (! empty($loc['streetAddress'])) {
            $address['streetAddress'] = $loc['streetAddress'];
        }
        if (! empty($loc['postalCode'])) {
            $address['postalCode'] = $loc['postalCode'];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $show->schema_name,
            'startDate' => $show->start_date,
            'endDate' => $show->end_date,
            'eventStatus' => self::eventStatusUrl($show->status),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'url' => $url,
            'image' => $site.$show->og_image,
            'description' => $show->event_description,
            'location' => [
                '@type' => 'Place',
                'name' => $loc['placeName'] ?? '',
                'address' => $address,
            ],
            'offers' => [
                '@type' => 'Offer',
                'name' => $offer['name'] ?? '',
                'price' => $offer['price'] ?? '',
                'priceCurrency' => $offer['priceCurrency'] ?? '',
                'availability' => $offer['availability'] ?? '',
                'url' => $url,
            ],
            'performer' => ['@type' => 'PerformingGroup', 'name' => $show->headliner],
            'organizer' => [
                '@type' => 'Organization',
                'name' => $organizer['name'] ?? '',
                'url' => $organizer['url'] ?? '',
            ],
            'isAccessibleForFree' => $show->admission === Admission::Free,
        ];
    }

    private static function eventStatusUrl(AirShowStatus $status): string
    {
        return match ($status) {
            AirShowStatus::Scheduled => 'https://schema.org/EventScheduled',
            AirShowStatus::Cancelled => 'https://schema.org/EventCancelled',
            AirShowStatus::Postponed => 'https://schema.org/EventPostponed',
        };
    }
}
