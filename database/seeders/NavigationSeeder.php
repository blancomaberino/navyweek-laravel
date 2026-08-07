<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Support\NavigationDefaults;
use Illuminate\Database\Seeder;

/**
 * Seeds the header/footer/legal navigation with the exact structure that used to
 * be hardcoded in the Blade partials, so a fresh install looks identical until an
 * editor changes it. Idempotent: menus upsert on their stable `key`, and each
 * menu's items upsert on `(menu_id, url)` — a link's target path is its stable
 * identity here, so re-running after a label reword updates in place rather than
 * duplicating (urls are unique within each default menu). The definitions come
 * from {@see NavigationDefaults}, the same source the render-time fallback uses,
 * so the seed and the fallback can never drift.
 */
class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NavigationDefaults::menus() as $definition) {
            $menu = Menu::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    'name' => $definition['name'],
                    'location' => $definition['location'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ],
            );

            foreach ($definition['items'] as $position => $item) {
                $menu->items()->updateOrCreate(
                    ['url' => $item['url']],
                    [
                        'label' => $item['label'],
                        // The header carries three extra facets a flat link list cannot
                        // express: which dynamic panel an item IS, the nav key that
                        // lights its tab, and its position in the mobile panel (which
                        // differs from the desktop bar). Absent for footer/legal items.
                        'slot' => $item['slot'] ?? null,
                        'active_slug' => $item['active_slug'] ?? null,
                        'target' => $item['target'] ?? null,
                        'rel' => $item['rel'] ?? null,
                        // An explicit `sort_order` wins over array position, so the
                        // definition can be read in one order and rendered in another.
                        'sort_order' => $item['sort_order'] ?? $position,
                        'mobile_sort_order' => $item['mobile_sort_order'] ?? null,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
