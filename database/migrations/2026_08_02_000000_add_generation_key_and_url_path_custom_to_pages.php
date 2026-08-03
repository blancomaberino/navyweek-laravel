<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decouples a generated page's IDENTITY from its LOCATION so URL paths become flexible:
 *
 *  - `generation_key` — the stable identity assigned by `pages:generate-*`. Upserts key
 *    on this (not `url_path`), so a page survives both a per-page rename and a
 *    family-wide prefix change (config('publishing.paths.*')). Null for editor-created
 *    pages, which generation never touches. Unique: one generated page per key.
 *
 *  - `url_path_is_custom` — set true when an editor renames the page in the admin panel
 *    (ChangeUrlPathAction). Regeneration then PRESERVES that url_path instead of snapping
 *    it back to the family default; a non-custom page keeps tracking the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('generation_key')->nullable()->unique()->after('slug');
            $table->boolean('url_path_is_custom')->default(false)->after('url_path');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropUnique(['generation_key']);
            $table->dropColumn(['generation_key', 'url_path_is_custom']);
        });
    }
};
