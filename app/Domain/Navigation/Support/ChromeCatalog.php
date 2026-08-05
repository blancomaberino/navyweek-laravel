<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Support;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Repositories\AirShowRepositoryInterface;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;

/**
 * Request-scoped source for the site chrome's data-driven bits: the "Deals"
 * mega-menu in the header and the "Deals" section above the footer (every
 * published discount-brand page), plus the static Events dropdown.
 *
 * Ported 1:1 from the legacy Header.tsx / DealsSection.tsx, which read the
 * build-time `discounts` registry. Here the same list comes from the published
 * discount-brand pages via {@see PageRepositoryInterface}. Memoized so the header
 * and footer view composers share a single query per request.
 */
final class ChromeCatalog
{
    /**
     * The legacy LOGO_DISPLAY_DEFAULT (src/data/discounts/logo.ts) — the caps a
     * brand gets when it carries no tuned `logoDisplay` of its own.
     */
    private const LOGO_MAX_HEIGHT = 28;

    private const LOGO_MAX_WIDTH = 130;

    /** @var list<array{brand: string, url: string, headline: string|null, category: string|null, logo: string|null, logoBackground: string|null, logoMaxHeight: int, logoMaxWidth: int}>|null */
    private ?array $deals = null;

    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly AirShowRepositoryInterface $airShows,
    ) {}

    /**
     * Every published discount-brand deal, newest published first (mirrors the
     * legacy DealsSection sort), for the mega-menu and the Deals section.
     *
     * @return list<array{brand: string, url: string, headline: string|null, category: string|null, logo: string|null, logoBackground: string|null, logoMaxHeight: int, logoMaxWidth: int}>
     */
    public function deals(): array
    {
        if ($this->deals !== null) {
            return $this->deals;
        }

        $rows = [];
        foreach ($this->pages->allPublishedDiscountBrandPages() as $page) {
            $offer = $page->pageable;
            if (! $offer instanceof Offer) {
                continue;
            }
            $connection = $offer->connection;

            $rows[] = [
                'brand' => $connection->brand,
                'url' => (string) $page->url_path,
                'headline' => $offer->headline_discount,
                'category' => $connection->category,
                'logo' => $connection->logo_url,
                // Per-brand chip colour (the legacy `logoBackground`). Most marks
                // need a white plate, but ~120 ship a light-on-dark logo and set
                // navy/black — hardcoding white made those chips glare.
                'logoBackground' => self::hexColour($connection->logo_background),
                // Per-brand image caps (the legacy `logoDisplay`). The brand marks
                // have wildly different aspect ratios — a long wordmark and a square
                // badge can't share one cap without one of them looking wrong — so
                // 95 records carry a tuned pair and the rest take the default.
                'logoMaxHeight' => self::logoCap($connection->logo_display, 'cardMaxHeight', self::LOGO_MAX_HEIGHT),
                'logoMaxWidth' => self::logoCap($connection->logo_display, 'cardMaxWidth', self::LOGO_MAX_WIDTH),
                'published' => $page->date_published?->toDateString() ?? '',
                'order' => $offer->sort_order ?? PHP_INT_MAX,
            ];
        }

        // Newest published first (mirrors the legacy DealsSection sort). The legacy
        // sort is STABLE, so equal dates keep the curated registry order — without
        // that tie-break the list diverges from the live page at the first tie.
        usort($rows, static fn (array $a, array $b): int => [$b['published'], $a['order']] <=> [$a['published'], $b['order']]);

        return $this->deals = array_map(
            static fn (array $d): array => [
                'brand' => $d['brand'],
                'url' => $d['url'],
                'headline' => $d['headline'],
                'category' => $d['category'],
                'logo' => $d['logo'],
                'logoBackground' => $d['logoBackground'],
                'logoMaxHeight' => $d['logoMaxHeight'],
                'logoMaxWidth' => $d['logoMaxWidth'],
            ],
            $rows,
        );
    }

    /**
     * The nav slug the current request should light up, or null for a page whose
     * family has no top-level nav item.
     *
     * The legacy header takes an explicit `activePage` slug from each page view
     * (`<Header activePage="schedule" />`) and compares it to the nav item's slug —
     * NOT to the current path. That distinction is the whole point: a city guide at
     * `/city/honolulu-hilo/` lights SCHEDULE, and a brand guide at
     * `/discount/yeti-military-veteran/` lights DEALS. Matching on path equality, as
     * this used to, left every detail page in the site with no active tab.
     *
     * The mapping is derived from `config('publishing.paths.*')` rather than route
     * literals, so a family-wide prefix change moves it too. Jet-team paths are
     * data-driven (`JetTeam.base_path`), so those come from the Events links.
     */
    public function activePage(string $path): ?string
    {
        $path = '/'.trim($path, '/').'/';
        $under = static fn (string $family): bool => str_starts_with($path, PagePaths::root($family));

        // A jet team lights its own hub (the legacy passes the team id).
        foreach ($this->eventLinks() as $link) {
            if (str_starts_with($path, $link['href'])) {
                return $link['slug'];
            }
        }

        return match (true) {
            // Brand guides, category hubs, the local-discount tree and the
            // credit-cards guide all light DEALS.
            $under('discounts'), $under('local_discounts'), $path === '/best-credit-cards-for-military/' => 'discount',
            // City guides light SCHEDULE, as CityDetail.tsx does.
            $under('navy_week_cities'), $path === '/schedule/' => 'schedule',
            // Everything else — including /contact/, which passes no activePage at
            // all — resolves to a slug with no matching nav item, so the legacy
            // highlights nothing. Verified against the live header on 10 paths.
            default => null,
        };
    }

    /**
     * One side of a brand's stored logo cap, in px.
     *
     * Like the chip colour this is editor-supplied and ends up in a `style`
     * attribute, so it is coerced to a positive int — a stored string can never be
     * anything but a number of pixels. A missing or nonsensical value takes the
     * legacy default rather than rendering an uncapped image.
     *
     * @param  array<string, mixed>|null  $display
     */
    private static function logoCap(?array $display, string $key, int $default): int
    {
        $value = $display[$key] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }

    /**
     * A stored colour, but only if it really is one.
     *
     * This value is editor-supplied and ends up inside a `style` attribute, where
     * Blade's HTML escaping stops an attribute break-out but not CSS injection
     * (`#fff; background-image: url(…)`). Restricting it to a 3/6-digit hex literal
     * means a stored string can never become anything but a colour; anything else
     * falls back to the stylesheet's default plate.
     */
    private static function hexColour(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value) === 1 ? $value : null;
    }

    /**
     * The Events dropdown — the four hub links, ported verbatim from Header.tsx.
     * Published air-show guides are indented UNDER "Air Shows Hub" (`children`),
     * exactly as the legacy `airShowSubLinks` render, so new shows appear as they
     * publish.
     *
     * @return list<array{label: string, href: string, slug: string, children: list<array{label: string, href: string, slug: string}>}>
     */
    public function eventLinks(): array
    {
        return [
            ['label' => 'Air Shows Hub', 'href' => '/air-show/', 'slug' => 'air-show', 'children' => $this->airShowSubLinks()],
            ['label' => 'Thunderbirds Hub', 'href' => '/thunderbirds/', 'slug' => 'thunderbirds', 'children' => []],
            ['label' => 'Blue Angels Hub', 'href' => '/blue-angels/', 'slug' => 'blue-angels', 'children' => []],
            ['label' => 'Fleet Week Hub', 'href' => '/fleetweek/', 'slug' => 'fleetweek', 'children' => []],
        ];
    }

    /**
     * @return list<array{label: string, href: string, slug: string}>
     */
    private function airShowSubLinks(): array
    {
        $links = $this->airShows->published()
            ->map(static fn (AirShow $show): array => [
                // The legacy uses the show's FULL name here (Header.tsx: `label: s.name`),
                // not the short one — "NAS Oceana Air Show", not "Oceana".
                'label' => $show->name,
                'href' => PagePaths::child('air_shows', $show->slug),
                'slug' => $show->slug,
            ])
            ->all();

        return array_values($links);
    }
}
