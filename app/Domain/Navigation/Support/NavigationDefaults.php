<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Support;

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
 */
final class NavigationDefaults
{
    /**
     * Full menu definitions (identity + placement + links), consumed by the seeder.
     *
     * @return list<array{key: string, name: string, location: MenuLocation, sort_order: int, items: list<array{label: string, url: string, target?: string, rel?: string}>}>
     */
    public static function menus(): array
    {
        return [
            [
                'key' => 'header-primary',
                'name' => 'Primary navigation',
                'location' => MenuLocation::Header,
                'sort_order' => 0,
                'items' => [
                    ['label' => 'Schedule', 'url' => '/schedule/'],
                    ['label' => 'Navy Bases', 'url' => '/navy-bases/'],
                    ['label' => 'Ranks', 'url' => '/navy-ranks/'],
                    ['label' => 'Air Shows', 'url' => '/air-show/'],
                    ['label' => 'Fleet Week', 'url' => '/fleetweek/'],
                    ['label' => 'Discounts', 'url' => '/discount/'],
                    ['label' => 'Veterans Day', 'url' => '/veterans-day/'],
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
                    ['label' => 'NAVCO (Official)', 'url' => 'https://outreach.navy.mil/Navy-Weeks/', 'target' => '_blank', 'rel' => 'noopener noreferrer'],
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
                    ['label' => 'Our Process', 'url' => '/our-process/'],
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
     * The header primary nav in the render view-model shape.
     *
     * @return list<array{label: string, href: string, target: string|null, rel: string|null, children: list<array{label: string, href: string, target: string|null, rel: string|null}>}>
     */
    public static function headerItems(): array
    {
        return self::itemsFor('header-primary');
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
     * @param  list<array{label: string, url: string, target?: string, rel?: string}>  $items
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
