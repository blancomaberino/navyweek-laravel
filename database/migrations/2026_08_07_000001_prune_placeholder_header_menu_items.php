<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the placeholder `header-primary` links with the real header.
 *
 * That menu was seeded with a seven-link list (Schedule / Navy Bases / Ranks / Air Shows
 * / Fleet Week / Discounts / Veterans Day) that matched nothing on the site, because the
 * header was hardcoded and never read it. Now that the bar renders FROM the menu, those
 * rows would appear in the live nav.
 *
 * It PRUNES AND WRITES, deliberately. Deploys run `migrate` without `db:seed`, and the
 * render-time fallback only engages on an EMPTY menu — so pruning alone would leave two
 * orphaned rows (`/schedule/` and `/discount/` are reused by the new header, so they are
 * not pruned) and every page would render a two-link bar until someone remembered to
 * seed. A data migration that deletes must leave the table renderable on its own.
 *
 * The rows are spelled as literals rather than read from NavigationDefaults: a migration
 * describes a point in time and must not change meaning when app code moves.
 */
return new class extends Migration
{
    /**
     * The exact rows the OLD seeder wrote, as (url => [label, sort_order]). All three
     * must match: an editor's own link to one of these paths carries a different label
     * or position and survives. Matching on url alone — or on "slot IS NULL", which is
     * an ordinary state for an editor's link — would delete their work.
     *
     * @var array<string, array{string, int}>
     */
    private const PLACEHOLDERS = [
        '/navy-bases/' => ['Navy Bases', 1],
        '/navy-ranks/' => ['Ranks', 2],
        '/air-show/' => ['Air Shows', 3],
        '/fleetweek/' => ['Fleet Week', 4],
        '/veterans-day/' => ['Veterans Day', 6],
    ];

    /**
     * The real header, in both orderings. Mirrors NavigationDefaults at the time of
     * writing; see the class docblock for why it is duplicated rather than imported.
     *
     * @var array<string, array<string, mixed>>
     */
    private const HEADER_ITEMS = [
        '/discount/' => ['label' => 'Deals', 'slot' => 'deals', 'active_slug' => 'discount', 'sort_order' => 0, 'mobile_sort_order' => 2],
        '/schedule/' => ['label' => 'Schedule', 'slot' => null, 'active_slug' => 'schedule', 'sort_order' => 1, 'mobile_sort_order' => 0],
        '/air-show/' => ['label' => 'Events', 'slot' => 'events', 'active_slug' => null, 'sort_order' => 2, 'mobile_sort_order' => 1],
        '/#partners' => ['label' => 'Partners', 'slot' => null, 'active_slug' => null, 'sort_order' => 3, 'mobile_sort_order' => 3],
        '/#faq' => ['label' => 'FAQ', 'slot' => null, 'active_slug' => null, 'sort_order' => 4, 'mobile_sort_order' => 4],
        '/contact/' => ['label' => 'Contact', 'slot' => null, 'active_slug' => null, 'sort_order' => 5, 'mobile_sort_order' => 5],
        'https://outreach.navy.mil/Navy-Weeks/' => ['label' => 'Official NAVCO Site', 'slot' => 'cta', 'active_slug' => null, 'target' => '_blank', 'rel' => 'noopener noreferrer', 'sort_order' => 6, 'mobile_sort_order' => 6],
    ];

    public function up(): void
    {
        $menuId = DB::table('menus')->where('key', 'header-primary')->value('id');

        if ($menuId === null) {
            return;
        }

        foreach (self::PLACEHOLDERS as $url => [$label, $sortOrder]) {
            DB::table('menu_items')
                ->where('menu_id', $menuId)
                ->where('url', $url)
                ->where('label', $label)
                ->where('sort_order', $sortOrder)
                ->whereNull('slot')
                ->delete();
        }

        $now = now();

        foreach (self::HEADER_ITEMS as $url => $attributes) {
            DB::table('menu_items')->updateOrInsert(
                ['menu_id' => $menuId, 'url' => $url],
                $attributes + [
                    'target' => null,
                    'rel' => null,
                    'parent_id' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        // Intentionally irreversible. Forward, it only removes rows that rendered
        // nowhere; rolling back the companion migration (which drops the columns)
        // leaves the pre-change code fully functional.
    }
};
