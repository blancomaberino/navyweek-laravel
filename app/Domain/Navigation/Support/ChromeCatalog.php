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
    /** @var list<array{brand: string, url: string, headline: string|null, category: string|null, logo: string|null, logoBackground: string|null}>|null */
    private ?array $deals = null;

    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly AirShowRepositoryInterface $airShows,
    ) {}

    /**
     * Every published discount-brand deal, newest published first (mirrors the
     * legacy DealsSection sort), for the mega-menu and the Deals section.
     *
     * @return list<array{brand: string, url: string, headline: string|null, category: string|null, logo: string|null, logoBackground: string|null}>
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
            ],
            $rows,
        );
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
                'label' => $show->short_name ?: $show->name,
                'href' => PagePaths::child('air_shows', $show->slug),
                'slug' => $show->slug,
            ])
            ->all();

        return array_values($links);
    }
}
