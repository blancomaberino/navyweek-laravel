<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Seo;

use App\Domain\Pillars\Models\Base;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Seo\BuildsSeoSchema;
use App\Domain\Publishing\Seo\SeoUrl;

/**
 * JSON-LD for a single naval-base page (`/navy-bases/{slug}/`), a 1:1 port of
 * `NavyBaseDetail.getSeoData` + `src/data/bases/seo.ts`. Emitted node list (after
 * `SeoHead` prepends the site Organization):
 *
 *   Organization → BreadcrumbList → Article → Place → GovernmentOrganization → FAQPage?
 *
 * The Article is the generic (org-authored) one shared with the other pillars —
 * bases carry no per-page Person byline. Place + GovernmentOrganization are the
 * base-specific nodes; FAQPage is emitted only when the base has FAQs (the legacy
 * graph omits the node entirely otherwise). Dates come from the page (build clock),
 * not the brief; the page-generation step seeds them from the base's last_updated.
 */
final class BasePageSchema
{
    use BuildsSeoSchema;

    /**
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, Base $base): array
    {
        $site = SeoUrl::site();
        $slug = $base->slug;
        $path = "/navy-bases/{$slug}/";
        $imagePath = "/og/bases/{$slug}.png";
        $url = SeoUrl::absolute($path);
        $image = $site.$imagePath;
        $overseas = $base->isOverseas();

        // Shared Home → Navy Bases prefix and the base's own final crumb; only the
        // middle differs (Overseas → country for OCONUS, else the state).
        $crumbs = [
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Navy Bases', 'url' => '/navy-bases/'],
            ...($overseas
                ? [
                    ['name' => 'Overseas', 'url' => '/navy-bases/overseas/'],
                    ['name' => (string) $base->country, 'url' => "/navy-bases/{$base->country_slug}/"],
                ]
                : [
                    ['name' => (string) $base->state_name, 'url' => "/navy-bases/{$base->state}/"],
                ]),
            ['name' => $base->name, 'url' => $path],
        ];

        $nodes = [
            self::breadcrumb($crumbs),
            self::article(
                headline: $base->meta_title,
                description: $base->meta_description,
                path: $path,
                imagePath: $imagePath,
                datePublished: self::isoDate($page->date_published),
                dateModified: self::isoDate($page->date_modified),
            ),
            self::placeNode($base, $url, $image, $overseas),
            self::governmentOrganizationNode($base, $url, $overseas),
        ];

        if ($base->faqs->isNotEmpty()) {
            $nodes[] = self::faqPageFrom($base->faqs);
        }

        return $nodes;
    }

    /**
     * The Place node (`buildBasePlaceSchema`): the installation as a physical place,
     * additionally typed as a GovernmentBuilding. `alternateName` emits `[]` (not
     * omitted) when the base has no aka, matching the legacy `aka ?? []`.
     *
     * @return array<string, mixed>
     */
    private static function placeNode(Base $base, string $url, string $image, bool $overseas): array
    {
        $address = [
            '@type' => 'PostalAddress',
            'addressLocality' => $base->city,
            'addressCountry' => $overseas
                ? ($base->country_iso2 ?? $base->country ?? '')
                : 'US',
        ];
        // addressRegion is the state (CONUS) or the county (overseas); omitted when blank.
        $region = $overseas ? $base->county : $base->state_abbr;
        if ($region !== null && $region !== '') {
            $address['addressRegion'] = $region;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Place',
            '@id' => "{$url}#place",
            'name' => $base->name,
            'alternateName' => $base->aka ?? [],
            'description' => $base->meta_description,
            'url' => $url,
            'image' => $image,
            'address' => $address,
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $base->lat,
                'longitude' => $base->lng,
            ],
            'additionalType' => 'https://schema.org/GovernmentBuilding',
        ];
    }

    /**
     * The GovernmentOrganization node (`buildBaseGovernmentOrganizationSchema`): the
     * command that runs the installation, parented to the U.S. Navy and located at
     * the Place node. `sameAs` drops any falsy Wikipedia/official URL.
     *
     * @return array<string, mixed>
     */
    private static function governmentOrganizationNode(Base $base, string $url, bool $overseas): array
    {
        $region = $overseas
            ? "{$base->city}, {$base->country}"
            : "{$base->city}, {$base->state_name}";

        $sameAs = array_values(array_filter(
            [$base->wikipedia_url, $base->official_url],
            static fn (?string $u): bool => $u !== null && $u !== '',
        ));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentOrganization',
            '@id' => "{$url}#org",
            'name' => $base->name,
            'alternateName' => $base->aka ?? [],
            'description' => "{$base->name} ({$base->type->label()}) — established {$base->established} in {$region}.",
            'url' => $url,
            'logo' => SeoUrl::site().'/favicon.svg',
            'foundingDate' => (string) $base->established,
            'parentOrganization' => [
                '@type' => 'GovernmentOrganization',
                'name' => 'United States Navy',
                'url' => 'https://www.navy.mil/',
            ],
            'location' => ['@id' => "{$url}#place"],
            'sameAs' => $sameAs,
        ];
    }
}
