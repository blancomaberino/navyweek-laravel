<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Support\PagePaths;
use App\Domain\Shared\Models\Faq;
use Illuminate\Support\Collection;

/**
 * JSON-LD for the home landing page (`/`), a 1:1 port of the legacy `Home.tsx` graph
 * (`src/lib/seo.ts` builders). Emitted node list (after `SeoHead` prepends Organization):
 *
 *   WebSite → BreadcrumbList(Home) → GovernmentOrganization(US Navy)
 *   → GovernmentOrganization(NAVCO) → ItemList(schedule) → FAQPage?
 *
 * The WebSite + the two GovernmentOrganization nodes are the shared `BuildsSeoSchema`
 * builders (also used by the Navy Week city graph). The ItemList is the 12-city schedule
 * — one `ListItem` per stop, its URL built via `PagePaths` (never a hardcoded `/city/`).
 * The FAQPage is emitted only when the page carries FAQs (seeded on its polymorphic
 * `faqs` by the generator), matching the legacy `buildFAQSchema(generalFaqs)`.
 */
final class HomePageSchema
{
    use BuildsSeoSchema;

    /**
     * @param  Collection<int, NavyWeekEvent>  $events  every stop in canonical order
     * @param  Collection<int, Faq>  $faqs  the home FAQs (may be empty)
     * @return list<array<string, mixed>>
     */
    public static function build(Page $page, Collection $events, Collection $faqs): array
    {
        $nodes = [
            self::webSite(),
            self::breadcrumb([
                ['name' => 'Home', 'url' => '/'],
            ]),
            self::usNavyOrganization(),
            self::navcoOrganization(),
            self::eventList($events),
        ];

        if ($faqs->isNotEmpty()) {
            $nodes[] = self::faqPageFrom($faqs);
        }

        return $nodes;
    }

    /**
     * The ItemList of Navy Week stops (port of `buildEventListSchema`): one `ListItem`
     * per event, in canonical order, linking each city's own page via `PagePaths` so the
     * whole family tracks `config('publishing.paths.navy_week_cities')`.
     *
     * @param  Collection<int, NavyWeekEvent>  $events
     * @return array<string, mixed>
     */
    private static function eventList(Collection $events): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Navy Week 2026 Schedule',
            'numberOfItems' => $events->count(),
            'itemListElement' => $events->values()->map(static fn (NavyWeekEvent $event, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => SeoUrl::absolute(PagePaths::child('navy_week_cities', $event->slug)),
                'name' => "Navy Week {$event->city} 2026",
            ])->all(),
        ];
    }
}
