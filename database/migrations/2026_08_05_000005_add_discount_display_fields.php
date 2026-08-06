<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two per-brand presentation values the discount guides render but the CMS had no
 * home for:
 *
 *  - connections.logo_display — the per-brand logo image cap ({cardMaxHeight,
 *    cardMaxWidth}). Brand wordmarks have wildly different aspect ratios, so one
 *    shared cap renders each at a different optical size; the hero chip scales the
 *    card cap by a fixed factor (see the legacy src/data/discounts/logo.ts).
 *  - offers.related_slugs — the curated "More military discounts" pins for a
 *    guide; unpinned guides fall back to catalogue order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table): void {
            $table->json('logo_display')->nullable()->after('logo_background');
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->json('related_slugs')->nullable()->after('sticky_cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table): void {
            $table->dropColumn('logo_display');
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->dropColumn('related_slugs');
        });
    }
};
