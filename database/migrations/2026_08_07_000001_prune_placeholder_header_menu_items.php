<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the four placeholder links left in the `header-primary` menu.
 *
 * That menu was seeded with a seven-link list (Schedule / Navy Bases / Ranks / Air Shows
 * / Fleet Week / Discounts / Veterans Day) that matched nothing on the site, because the
 * header was hardcoded and never read it. Now that the header renders FROM the menu,
 * those rows would appear in the live nav.
 *
 * Done here rather than in NavigationSeeder because the seeder upserts on `url` and must
 * stay non-destructive — an editor's own links must survive a re-seed. These four are
 * named explicitly, so nothing an editor added can be caught by it.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PLACEHOLDER_URLS = [
        '/navy-bases/',
        '/navy-ranks/',
        '/air-show/',
        '/fleetweek/',
        '/veterans-day/',
    ];

    public function up(): void
    {
        $menuId = DB::table('menus')->where('key', 'header-primary')->value('id');

        if ($menuId === null) {
            return;
        }

        DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereIn('url', self::PLACEHOLDER_URLS)
            // Belt and braces: never touch a row that carries the header facets, so a
            // future item legitimately pointing at one of these paths is safe.
            ->whereNull('slot')
            ->whereNull('mobile_sort_order')
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: these rows rendered nowhere and describe a nav
        // that never existed. Re-running NavigationSeeder restores the real header.
    }
};
