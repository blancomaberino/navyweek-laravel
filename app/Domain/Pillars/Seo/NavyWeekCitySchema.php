<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Seo;

use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Seo\BuildsSeoSchema;
use App\Domain\Publishing\Seo\SeoUrl;
use Illuminate\Support\Carbon;

/**
 * JSON-LD for a Navy Week city page (`/city/{slug}/`), a 1:1 port of
 * `CityDetail.getSeoData` + `buildEventSchema`/`buildUsNavyOrganizationSchema`/
 * `buildNavcoOrganizationSchema` (`src/lib/seo.ts`). Emitted node list (after
 * `SeoHead` prepends Organization):
 *
 *   BreadcrumbList → GovernmentOrganization(US Navy) → GovernmentOrganization(NAVCO)
 *   → Event(+subEvent) → FAQPage?
 *
 * NO Article/WebPage/Person nodes (unlike the discount/air-show/fleet-week guides).
 * The Event is the richest node: performers are regex-derived from `navy_assets`
 * (with a Navy-Band/Leap-Frogs fallback), and one nested `subEvent` is emitted per
 * stored daily-schedule item, located at its matched venue (or the parent city
 * location). The rich city detail (navy_assets/highlights/venues/daily_schedule) is
 * folded into the NavyWeekEvent row's JSON columns.
 *
 * ACCEPTED DEVIATION: the legacy renderer synthesized placeholder "Daily schedule TBA"
 * subEvents for cities with no published schedule. The Stage-A exporter deliberately
 * did NOT store that synthesis ("display-only, not stored"), so cities with a null
 * `daily_schedule` emit no subEvent here — matching the migration's stored data. The
 * item set for cities WITH a schedule is byte-identical; only the thin TBA placeholders
 * are dropped.
 */
final class NavyWeekCitySchema
{
    use BuildsSeoSchema;

    /**
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, NavyWeekEvent $event): array
    {
        $slug = $event->slug;

        $nodes = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Schedule', 'url' => '/schedule/'],
                ['name' => $event->city, 'url' => "/city/{$slug}/"],
            ]),
            self::usNavyOrganization(),
            self::navcoOrganization(),
            self::eventNode($event),
        ];

        if ($event->faqs->isNotEmpty()) {
            $nodes[] = self::faqPageFrom($event->faqs);
        }

        return $nodes;
    }

    /** @return array<string, mixed> */
    private static function usNavyOrganization(): array
    {
        $site = SeoUrl::site();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentOrganization',
            '@id' => "{$site}/#us-navy",
            'name' => 'United States Navy',
            'alternateName' => 'U.S. Navy',
            'url' => 'https://www.navy.mil/',
            'sameAs' => [
                'https://www.navy.mil/',
                'https://en.wikipedia.org/wiki/United_States_Navy',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function navcoOrganization(): array
    {
        $site = SeoUrl::site();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentOrganization',
            '@id' => "{$site}/#navco",
            'name' => 'Navy Office of Community Outreach',
            'alternateName' => 'NAVCO',
            'url' => 'https://outreach.navy.mil/Navy-Weeks/',
            'description' => "The Navy Office of Community Outreach (NAVCO), based in Millington, TN, manages the U.S. Navy Week program — the Navy's flagship community outreach effort in cities without a significant Navy presence.",
            'parentOrganization' => ['@id' => "{$site}/#us-navy"],
            'sameAs' => [
                'https://outreach.navy.mil/Navy-Weeks/',
                'https://outreach.navy.mil/',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function eventNode(NavyWeekEvent $event): array
    {
        $site = SeoUrl::site();
        $slug = $event->slug;
        $pageUrl = "{$site}/city/{$slug}/";
        $ogImage = "{$site}/og/{$slug}.png";

        $performers = self::performers($event->navy_assets ?? []);
        $highlights = $event->highlights ?? [];
        $range = self::formatDateRange($event->start_date, $event->end_date);

        // Two description variants, keyed on whether the city has rich detail (the
        // legacy `cityData` — folded here into navy_assets/highlights). The Stage-A
        // exporter throws when cityData is missing and every migrated city has
        // non-empty highlights, so `highlights !== []` is a faithful proxy for the
        // legacy `cityData` truthiness (the fallback branch is dead for real data).
        $description = $highlights !== []
            ? "U.S. Navy Week arrives in {$event->city}, {$event->state} from {$range}, coinciding with the {$event->anchor_event}. ".implode(', ', array_slice($highlights, 0, 3)).', and more free public events.'
            : "U.S. Navy Week arrives in {$event->city}, {$event->state} coinciding with the {$event->anchor_event}. From {$range}, experience ship tours, Blue Angels demonstrations, Navy Band performances, and STEM exhibits.";

        $parentLocation = [
            '@type' => 'Place',
            'name' => "{$event->city}, {$event->state}",
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $event->city,
                'addressRegion' => $event->state_abbr,
                'addressCountry' => 'US',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                // Cast the decimal:7 string to a float so the parent geo emits a JSON
                // number (matching legacy + the numeric venue geo in subEvents).
                'latitude' => (float) $event->lat,
                'longitude' => (float) $event->lng,
            ],
        ];

        $offers = [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
            'url' => $pageUrl,
            'validFrom' => '2026-01-01',
        ];

        $subEvents = self::subEvents($event, $parentLocation, $performers, $offers, $ogImage, $pageUrl);

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => "Navy Week {$event->city} 2026",
            'description' => $description,
            'startDate' => $event->start_date->format('Y-m-d'),
            'endDate' => $event->end_date->format('Y-m-d'),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => $parentLocation,
            'organizer' => ['@id' => "{$site}/#navco"],
            'performer' => $performers,
            'offers' => $offers,
            'image' => $ogImage,
            'url' => $pageUrl,
            'isAccessibleForFree' => true,
        ];
        if ($subEvents !== []) {
            $node['subEvent'] = $subEvents;
        }

        return $node;
    }

    /**
     * Regex-derive the PerformingGroup performers from the city's `navy_assets`,
     * falling back to Navy Band + Leap Frogs when nothing matches (port of the
     * `buildEventSchema` performer loop). Insertion order preserved.
     *
     * @param  array<int, string>  $navyAssets
     * @return list<array{'@type': string, name: string}>
     */
    private static function performers(array $navyAssets): array
    {
        /** @var array<string, true> $names */
        $names = [];
        foreach ($navyAssets as $asset) {
            if (preg_match('/blue angels/i', $asset)) {
                $names['U.S. Navy Blue Angels Flight Demonstration Squadron'] = true;
            }
            if (preg_match('/Navy Band Southeast/i', $asset)) {
                $names['U.S. Navy Band Southeast'] = true;
            } elseif (preg_match('/Pacific Fleet Band/i', $asset)) {
                $names['U.S. Pacific Fleet Band'] = true;
            } elseif (preg_match('/Fleet Forces Band/i', $asset)) {
                $names['U.S. Fleet Forces Band'] = true;
            } elseif (preg_match('/Navy Band\b/i', $asset) && ! preg_match('/Navy bands/i', $asset)) {
                $names['U.S. Navy Band'] = true;
            }
            if (preg_match('/Leap Frog/i', $asset)) {
                $names['U.S. Navy Leap Frogs Parachute Team'] = true;
            }
            if (preg_match('/Ceremonial Guard/i', $asset)) {
                $names['U.S. Navy Ceremonial Guard'] = true;
            }
            if (preg_match('/F-35C/i', $asset) && preg_match('/Demo/i', $asset)) {
                $names['Navy F-35C Lightning II Demo Team'] = true;
            }
        }
        if ($names === []) {
            $names['U.S. Navy Band'] = true;
            $names['U.S. Navy Leap Frogs Parachute Team'] = true;
        }

        return array_map(
            static fn (string $name): array => ['@type' => 'PerformingGroup', 'name' => $name],
            array_keys($names),
        );
    }

    /**
     * One nested `subEvent` per daily-schedule item, located at its matched venue
     * (looked up by name from `venues`) or the parent city location.
     *
     * @param  array<string, mixed>  $parentLocation
     * @param  list<array{'@type': string, name: string}>  $performers
     * @param  array<string, mixed>  $offers
     * @return list<array<string, mixed>>
     */
    private static function subEvents(
        NavyWeekEvent $event,
        array $parentLocation,
        array $performers,
        array $offers,
        string $ogImage,
        string $pageUrl,
    ): array {
        $days = $event->daily_schedule ?? [];
        if ($days === []) {
            return [];
        }

        // Venue lookup by name.
        /** @var array<string, array<string, mixed>> $venueLookup */
        $venueLookup = [];
        foreach ($event->venues ?? [] as $venue) {
            if (isset($venue['name']) && is_string($venue['name'])) {
                $venueLookup[$venue['name']] = $venue;
            }
        }

        $subEvents = [];
        foreach ($days as $day) {
            $date = is_string($day['date'] ?? null) ? $day['date'] : '';
            $items = is_array($day['items'] ?? null) ? $day['items'] : [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $title = is_string($item['title'] ?? null) ? $item['title'] : '';
                $venueName = is_string($item['venue'] ?? null) ? $item['venue'] : null;
                $matched = $venueName !== null ? ($venueLookup[$venueName] ?? null) : null;
                $location = $matched !== null ? self::venuePlace($matched, $event) : $parentLocation;

                $sub = [
                    '@type' => 'Event',
                    'name' => "{$title} — Navy Week {$event->city}",
                    'startDate' => $date,
                    'endDate' => $date,
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                    'eventStatus' => 'https://schema.org/EventScheduled',
                    'location' => $location,
                    'organizer' => ['@id' => SeoUrl::site().'/#organization'],
                    'performer' => $performers,
                    'image' => $ogImage,
                    'offers' => $offers,
                    'isAccessibleForFree' => true,
                ];
                if (! empty($item['description'])) {
                    $sub['description'] = $item['description'];
                }
                $sub['url'] = ! empty($item['source']) ? $item['source'] : $pageUrl;

                $subEvents[] = $sub;
            }
        }

        return $subEvents;
    }

    /**
     * A Place node for a matched venue — with a PostalAddress when the venue has a
     * street address, and GeoCoordinates when it has numeric lat/lng.
     *
     * @param  array<string, mixed>  $venue
     * @return array<string, mixed>
     */
    private static function venuePlace(array $venue, NavyWeekEvent $event): array
    {
        $place = ['@type' => 'Place', 'name' => $venue['name'] ?? ''];
        if (! empty($venue['address'])) {
            $place['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $venue['address'],
                'addressLocality' => $event->city,
                'addressRegion' => $event->state_abbr,
                'addressCountry' => 'US',
            ];
        }
        if (is_numeric($venue['lat'] ?? null) && is_numeric($venue['lng'] ?? null)) {
            $place['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $venue['lat'],
                'longitude' => $venue['lng'],
            ];
        }

        return $place;
    }

    /**
     * The page `<title>` — `{city} Navy Week 2026: Dates, Schedule, Events & {anchor} |
     * NavyWeek.org` (port of `CityDetail.getSeoData` seoTitle). Used by page generation.
     */
    public static function metaTitle(NavyWeekEvent $event): string
    {
        return "{$event->city} Navy Week 2026: Dates, Schedule, Events & {$event->anchor_event} | NavyWeek.org";
    }

    /**
     * The page meta description (port of `buildCityMetaDescription`): a lead sentence
     * plus as many highlights as fit under 160 chars. Used by page generation.
     */
    public static function metaDescription(NavyWeekEvent $event): string
    {
        $highlights = $event->highlights ?? [];
        $head = "{$event->city} Navy Week 2026 runs ".self::formatDateRange($event->start_date, $event->end_date)." with the {$event->anchor_event}. ";
        $tail = 'See the full schedule, venues, parking, and costs.';

        $best = $head.$tail;
        for ($k = 1; $k <= count($highlights); $k++) {
            $candidate = $head.'Free events: '.implode(', ', array_slice($highlights, 0, $k)).'. '.$tail;
            if (mb_strlen($candidate) > 160) {
                break;
            }
            $best = $candidate;
        }

        return $best;
    }

    /**
     * Port of `formatDateRange` (data.ts): "September 26 – 28, 2026" within one month,
     * else "September 26 – October 3, 2026". En-dash with surrounding spaces; the start
     * year is used for both.
     */
    private static function formatDateRange(Carbon $start, Carbon $end): string
    {
        if ($start->month === $end->month) {
            return $start->format('F j').' – '.$end->format('j').', '.$start->format('Y');
        }

        return $start->format('F j').' – '.$end->format('F j').', '.$start->format('Y');
    }
}
