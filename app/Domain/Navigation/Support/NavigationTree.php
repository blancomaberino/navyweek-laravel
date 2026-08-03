<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Support;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Models\MenuItem;
use App\Domain\Navigation\Repositories\MenuRepositoryInterface;
use Throwable;

/**
 * Builds the render-ready navigation view-model for the header/footer partials
 * from the editable menu data, and falls back to {@see NavigationDefaults} when a
 * region has no active menu (or the tables are missing — e.g. a request served
 * before the migration has run).
 *
 * Registered as a request-scoped singleton, and memoizes each region's result so
 * the header and footer composers (bound to two partials) trigger the underlying
 * reads at most once per request.
 *
 * Every method returns plain arrays (not Eloquent models) in one shape so the
 * Blade partials — and their fallback path — stay identical and framework-free.
 *
 * @phpstan-type NavLink array{label: string, href: string, target: string|null, rel: string|null}
 * @phpstan-type NavItem array{label: string, href: string, target: string|null, rel: string|null, children: list<NavLink>}
 * @phpstan-type NavGroup array{heading: string, links: list<NavItem>}
 */
final class NavigationTree
{
    /** @var list<NavItem>|null */
    private ?array $headerCache = null;

    /** @var list<NavGroup>|null */
    private ?array $footerCache = null;

    /** @var list<NavItem>|null */
    private ?array $legalCache = null;

    public function __construct(private readonly MenuRepositoryInterface $menus) {}

    /**
     * The header primary nav links, in order (with any dropdown children nested).
     *
     * @return list<NavItem>
     */
    public function header(): array
    {
        return $this->headerCache ??= $this->firstMenuItems(MenuLocation::Header)
            ?? NavigationDefaults::headerItems();
    }

    /**
     * The footer link columns as `[heading, links]` groups, in order.
     *
     * @return list<NavGroup>
     */
    public function footerGroups(): array
    {
        return $this->footerCache ??= $this->computeFooterGroups();
    }

    /**
     * The legal row links, in order.
     *
     * @return list<NavItem>
     */
    public function legal(): array
    {
        return $this->legalCache ??= $this->firstMenuItems(MenuLocation::Legal)
            ?? NavigationDefaults::legalItems();
    }

    /**
     * @return list<NavGroup>
     */
    private function computeFooterGroups(): array
    {
        try {
            $menus = $this->menus->activeMenusForLocation(MenuLocation::Footer);
        } catch (Throwable) {
            return NavigationDefaults::footerGroups();
        }

        if ($menus->isEmpty()) {
            return NavigationDefaults::footerGroups();
        }

        // The map reads $menu->activeItems / $item->activeChildren, which the
        // repository eager-loads — so this stays outside the try (no lazy load).
        return array_values($menus
            ->map(fn (Menu $menu): array => [
                'heading' => $menu->name,
                'links' => $this->mapItems($menu->activeItems),
            ])
            ->all());
    }

    /**
     * The mapped items of the first active menu in a region, or null if there is
     * none (missing/unseeded table or an editor that emptied the region) — the
     * caller then substitutes the hardcoded default.
     *
     * @return list<NavItem>|null
     */
    private function firstMenuItems(MenuLocation $location): ?array
    {
        try {
            $menu = $this->menus->activeMenusForLocation($location)->first();
        } catch (Throwable) {
            return null;
        }

        if (! $menu instanceof Menu || $menu->activeItems->isEmpty()) {
            return null;
        }

        return $this->mapItems($menu->activeItems);
    }

    /**
     * @param  iterable<int, MenuItem>  $items
     * @return list<NavItem>
     */
    private function mapItems(iterable $items): array
    {
        $mapped = [];
        foreach ($items as $item) {
            $mapped[] = $this->mapItem($item) + [
                'children' => array_values(array_map(
                    fn (MenuItem $child): array => $this->mapItem($child),
                    $item->activeChildren->all(),
                )),
            ];
        }

        return $mapped;
    }

    /**
     * @return NavLink
     */
    private function mapItem(MenuItem $item): array
    {
        $target = $item->target;
        $rel = $item->rel;

        // A new-tab link with no explicit rel gets the safe default (reverse-
        // tabnabbing / referrer leakage guard).
        if ($target === '_blank' && ($rel === null || $rel === '')) {
            $rel = 'noopener noreferrer';
        }

        return [
            'label' => $item->label,
            // Neutralize any disallowed scheme (e.g. javascript:) at render.
            'href' => LinkUrl::sanitize($item->url),
            'target' => $target,
            'rel' => $rel,
        ];
    }
}
