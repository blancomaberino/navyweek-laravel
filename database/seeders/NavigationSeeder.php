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
                        'target' => $item['target'] ?? null,
                        'rel' => $item['rel'] ?? null,
                        'sort_order' => $position,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
