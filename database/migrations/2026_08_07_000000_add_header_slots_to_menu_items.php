<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three things the header needs that a flat link list cannot express, so the
 * `header` menu can actually drive the rendered nav instead of sitting unread.
 *
 * - `slot` marks an item as one of the dynamic panels (the Deals mega-menu, the Events
 *   dropdown) or the off-site CTA. Without it the menu could carry labels but not
 *   ORDER, because those panels would stay pinned in the markup.
 * - `active_slug` is the nav key a page reports to light its tab. It is deliberately
 *   NOT derived from `url`: a detail page lights its FAMILY's tab (`/navy-bases/norfolk/`
 *   lights "Navy Bases"), which is a match on slug, not on path.
 * - `mobile_sort_order` exists because the two orders genuinely differ — desktop leads
 *   with Deals, the mobile panel leads with Schedule. Null falls back to `sort_order`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('slot')->nullable()->after('url');
            $table->string('active_slug')->nullable()->after('slot');
            $table->unsignedInteger('mobile_sort_order')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['slot', 'active_slug', 'mobile_sort_order']);
        });
    }
};
