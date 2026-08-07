<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Support;

use App\Domain\Navigation\Enums\MenuItemSlot;
use App\Domain\Navigation\Enums\MenuLocation;
use Database\Seeders\NavigationSeeder;

/**
 * The canonical default navigation — the exact header/footer structure that was
 * hardcoded in the Blade partials before menus became editable.
 *
 * This is the single source of truth for BOTH:
 * - the {@see NavigationSeeder} (so a fresh install ships the
 *   current links unchanged — "nothing changes visually at first"), and
 * - the render-time fallback in {@see NavigationTree} (so the chrome never paints
 *   empty if the tables are missing/unseeded or a region has no active menu).
 *
 * Keeping them in one place guarantees the fallback and the seed can never drift.
 *
 * @phpstan-type HeaderItem array{label: string, href: string, slot: MenuItemSlot|null, activeSlug: string|null, target: string|null, rel: string|null}
 */
final class NavigationDefaults
{
    /**
     * Full menu definitions (identity + placement + links), consumed by the seeder.
     *
     * @return list<array{key: string, name: string, location: MenuLocation, sort_order: int, items: list<array{label: string, url: string, slot?: MenuItemSlot, active_slug?: string, target?: string, rel?: string, sort_order?: int, mobile_sort_order?: int}>}>
     */
    public static function menus(): array
    {
        return [
            [
                'key' => 'header-primary',
                'name' => 'Primary navigation',
                'location' => MenuLocation::Header,
                'sort_order' => 0,
                // The REAL rendered header, ported from src/components/Header.tsx —
                // not the seven-link placeholder this used to hold, which matched
                // nothing on the site and rendered nowhere.
                //
                // `sort_order` is the desktop bar; `mobile_sort_order` is the slide-out
                // panel, which deliberately leads with Schedule where the desktop bar
                // leads with Deals. `slot` marks the two panels whose CONTENTS come
                // from the catalog, and the off-site CTA.
                'items' => [
                    [
                        'label' => 'Deals',
                        'url' => '/discount/',
                        'slot' => MenuItemSlot::Deals,
                        'active_slug' => 'discount',
                        'sort_order' => 0,
                        'mobile_sort_order' => 2,
                    ],
                    [
                        'label' => 'Schedule',
                        'url' => '/schedule/',
                        'active_slug' => 'schedule',
                        'sort_order' => 1,
                        'mobile_sort_order' => 0,
                    ],
                    [
                        'label' => 'Events',
                        'url' => '/air-show/',
                        'slot' => MenuItemSlot::Events,
                        'sort_order' => 2,
                        'mobile_sort_order' => 1,
                    ],
                    // Both are in-page anchors on the home page, so neither carries an
                    // active slug — the legacy renders them with a bare class.
                    ['label' => 'Partners', 'url' => '/#partners', 'sort_order' => 3, 'mobile_sort_order' => 3],
                    ['label' => 'FAQ', 'url' => '/#faq', 'sort_order' => 4, 'mobile_sort_order' => 4],
                    [
                        'label' => 'Contact',
                        'url' => '/contact/',
                        'active_slug' => 'contact',
                        'sort_order' => 5,
                        'mobile_sort_order' => 5,
                    ],
                    [
                        'label' => 'Official NAVCO Site',
                        'url' => 'https://outreach.navy.mil/Navy-Weeks/',
                        'slot' => MenuItemSlot::Cta,
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                        'sort_order' => 6,
                        'mobile_sort_order' => 6,
                    ],
                ],
            ],
            [
                'key' => 'footer-navy-week',
                'name' => 'Navy Week',
                'location' => MenuLocation::Footer,
                'sort_order' => 0,
                'items' => [
                    ['label' => 'Schedule', 'url' => '/schedule/'],
                    ['label' => 'Map', 'url' => '/map/'],
                    ['label' => 'Contact', 'url' => '/contact/'],
                    ['label' => 'Official NAVCO Site', 'url' => 'https://outreach.navy.mil/Navy-Weeks/', 'target' => '_blank', 'rel' => 'noopener noreferrer'],
                ],
            ],
            [
                'key' => 'footer-navy-reference',
                'name' => 'Navy Reference',
                'location' => MenuLocation::Footer,
                'sort_order' => 1,
                'items' => [
                    ['label' => 'Navy Reference', 'url' => '/navy-reference/'],
                    ['label' => 'Navy Bases', 'url' => '/navy-bases/'],
                    ['label' => 'Navy Ranks', 'url' => '/navy-ranks/'],
                    ['label' => 'Navy Ratings', 'url' => '/navy-ratings/'],
                    ['label' => 'Designators', 'url' => '/navy-designators/'],
                ],
            ],
            [
                'key' => 'footer-shows',
                'name' => 'Shows & Fleet Week',
                'location' => MenuLocation::Footer,
                'sort_order' => 2,
                'items' => [
                    ['label' => 'Air Shows', 'url' => '/air-show/'],
                    ['label' => 'Fleet Week', 'url' => '/fleetweek/'],
                    ['label' => 'Blue Angels', 'url' => '/blue-angels/'],
                    ['label' => 'Thunderbirds', 'url' => '/thunderbirds/'],
                ],
            ],
            [
                'key' => 'footer-veterans',
                'name' => 'Veterans & Benefits',
                'location' => MenuLocation::Footer,
                'sort_order' => 3,
                'items' => [
                    ['label' => 'VA Disability', 'url' => '/va-disability/'],
                    ['label' => 'Veterans Home Care', 'url' => '/veterans-home-care/'],
                    ['label' => 'Veterans Day', 'url' => '/veterans-day/'],
                    ['label' => 'Veterans Day Free Meals', 'url' => '/veterans-day/free-meals/'],
                    ['label' => 'Military Discounts', 'url' => '/discount/'],
                    ['label' => 'About the Editor', 'url' => '/authors/t-alford/'],
                    ['label' => 'Our Process', 'url' => '/our-process/'],
                    ['label' => 'Service Dogs', 'url' => 'https://www.servicedogs.com', 'target' => '_blank', 'rel' => 'noopener noreferrer'],
                ],
            ],
            [
                'key' => 'footer-legal',
                'name' => 'Legal',
                'location' => MenuLocation::Legal,
                'sort_order' => 0,
                'items' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy/'],
                    ['label' => 'Terms', 'url' => '/terms/'],
                    ['label' => 'Contact Us', 'url' => '/contact/'],
                ],
            ],
        ];
    }

    /**
     * The header nav in the render view-model shape, ordered for the DESKTOP bar.
     *
     * @return list<HeaderItem>
     */
    public static function headerItems(): array
    {
        return self::mapHeaderItems('sort_order');
    }

    /**
     * The same items ordered for the MOBILE panel, which leads with Schedule where the
     * desktop bar leads with Deals.
     *
     * @return list<HeaderItem>
     */
    public static function headerMobileItems(): array
    {
        return self::mapHeaderItems('mobile_sort_order');
    }

    /**
     * @return list<HeaderItem>
     */
    private static function mapHeaderItems(string $orderBy): array
    {
        foreach (self::menus() as $menu) {
            if ($menu['key'] !== 'header-primary') {
                continue;
            }

            $items = $menu['items'];

            usort(
                $items,
                static fn (array $a, array $b): int => ($a[$orderBy] ?? $a['sort_order'] ?? 0)
                    <=> ($b[$orderBy] ?? $b['sort_order'] ?? 0),
            );

            return array_map(static fn (array $item): array => [
                'label' => $item['label'],
                'href' => $item['url'],
                'slot' => $item['slot'] ?? null,
                'activeSlug' => $item['active_slug'] ?? null,
                'target' => $item['target'] ?? null,
                'rel' => $item['rel'] ?? null,
            ], $items);
        }

        return [];
    }

    /**
     * The footer link columns as `[heading, links]` groups, in order.
     *
     * @return list<array{heading: string, links: list<array{label: string, href: string, target: string|null, rel: string|null, children: list<array{label: string, href: string, target: string|null, rel: string|null}>}>}>
     */
    public static function footerGroups(): array
    {
        $groups = [];
        foreach (self::menus() as $menu) {
            if ($menu['location'] === MenuLocation::Footer) {
                $groups[] = [
                    'heading' => $menu['name'],
                    'links' => self::mapItems($menu['items']),
                ];
            }
        }

        return $groups;
    }

    /**
     * The legal row links in the render view-model shape.
     *
     * @return list<array{label: string, href: string, target: string|null, rel: string|null, children: list<array{label: string, href: string, target: string|null, rel: string|null}>}>
     */
    public static function legalItems(): array
    {
        return self::itemsFor('footer-legal');
    }

    /**
     * @return list<array{label: string, href: string, target: string|null, rel: string|null, children: list<array{label: string, href: string, target: string|null, rel: string|null}>}>
     */
    private static function itemsFor(string $key): array
    {
        foreach (self::menus() as $menu) {
            if ($menu['key'] === $key) {
                return self::mapItems($menu['items']);
            }
        }

        return [];
    }

    /**
     * @param  list<array{label: string, url: string, slot?: MenuItemSlot, active_slug?: string, target?: string, rel?: string, sort_order?: int, mobile_sort_order?: int}>  $items
     * @return list<array{label: string, href: string, target: string|null, rel: string|null, children: list<array{label: string, href: string, target: string|null, rel: string|null}>}>
     */
    private static function mapItems(array $items): array
    {
        return array_map(static fn (array $item): array => [
            'label' => $item['label'],
            'href' => $item['url'],
            'target' => $item['target'] ?? null,
            'rel' => $item['rel'] ?? null,
            'children' => [],
        ], $items);
    }
}
