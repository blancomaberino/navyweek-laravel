<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable navigation menus — the header primary nav, the footer link columns and
 * the legal row that were hardcoded in the Blade partials, now managed in Filament.
 * One row per menu; `key` is the stable identity the seeder/lookups pin to,
 * `location` + `sort_order` place it in the site chrome, `name` is the heading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            // Stable identity (e.g. header-primary, footer-navy-week); the mutable
            // `location`/`name` are presentation, so lookups pin to this instead.
            $table->string('key')->unique();
            $table->string('name');
            // header | footer | legal — see App\Domain\Navigation\Enums\MenuLocation.
            $table->string('location');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Backs the ordered "active menus for a region" read (the header/footer
            // composers). Explicit because Postgres does not auto-index this.
            $table->index(['location', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
