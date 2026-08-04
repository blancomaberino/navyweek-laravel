<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Support;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

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
    /** @var list<array{brand: string, url: string, headline: string|null, category: string|null, logo: string|null}>|null */
    private ?array $deals = null;

    public function __construct(private readonly PageRepositoryInterface $pages) {}

    /**
     * Every published discount-brand deal, newest published first (mirrors the
     * legacy DealsSection sort), for the mega-menu and the Deals section.
     *
     * @return list<array{brand: string, url: string, headline: string|null, category: string|null, logo: string|null}>
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
                'published' => $page->date_published?->toDateString() ?? '',
            ];
        }

        // Newest published first (mirrors the legacy DealsSection sort); ISO dates
        // compare correctly as strings and usort keeps it a list.
        usort($rows, static fn (array $a, array $b): int => $b['published'] <=> $a['published']);

        return $this->deals = array_map(
            static fn (array $d): array => [
                'brand' => $d['brand'],
                'url' => $d['url'],
                'headline' => $d['headline'],
                'category' => $d['category'],
                'logo' => $d['logo'],
            ],
            $rows,
        );
    }

    /**
     * The Events dropdown — the four hub links, ported verbatim from Header.tsx.
     *
     * @return list<array{label: string, href: string, slug: string}>
     */
    public function eventLinks(): array
    {
        return [
            ['label' => 'Air Shows Hub', 'href' => '/air-show/', 'slug' => 'air-show'],
            ['label' => 'Thunderbirds Hub', 'href' => '/thunderbirds/', 'slug' => 'thunderbirds'],
            ['label' => 'Blue Angels Hub', 'href' => '/blue-angels/', 'slug' => 'blue-angels'],
            ['label' => 'Fleet Week Hub', 'href' => '/fleetweek/', 'slug' => 'fleetweek'],
        ];
    }
}
