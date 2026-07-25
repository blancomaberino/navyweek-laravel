<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the routing-only `pages` table (slice 1) with the SEO / JSON-LD layer
 * and the polymorphic `pageable` owner. These columns are the DB successors to the
 * legacy `SEOProps`/`SEOData` (src/lib/seo.ts): head meta, canonical override, OG,
 * robots, the Article `datePublished`/`dateModified` build-clock dates, and a slot
 * for page-specific extra JSON-LD nodes.
 *
 * All columns are nullable / defaulted so the routing rows created in slice 1 stay
 * valid; the render layer (Phase 3) requires title + description for published pages.
 * Derived schema (auto Organization, aggregate-driven Article/LocalBusiness) is
 * composed at render time from `pageable` — only page-specific extras are stored in
 * `json_ld`, so the graph never drifts from the aggregate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Head meta — <title>/og:title/twitter:title and the meta description.
            $table->string('title')->nullable()->after('url_path');
            $table->text('meta_description')->nullable()->after('title');
            // Canonical override for router/disambiguation pages (legacy canonicalPath).
            $table->string('canonical_path')->nullable()->after('meta_description');
            // Open Graph: og:type (default website) + a site-relative image path the
            // render layer prefixes with the host, falling back to /og/home.png.
            $table->string('og_type')->default('website')->after('canonical_path');
            $table->string('og_image_path')->nullable()->after('og_type');
            // robots noindex,nofollow — also suppresses the auto Organization node.
            $table->boolean('noindex')->default(false)->after('og_image_path');
            // Article dates, driven by the build clock (build-clock rule): first build
            // sets date_published (preserved verbatim); every build sets date_modified.
            $table->timestampTz('date_published')->nullable()->after('noindex');
            $table->timestampTz('date_modified')->nullable()->after('date_published');
            // Page-specific EXTRA schema nodes (FAQPage, custom static-page graphs,
            // author overrides). Merged after the derived schema at render.
            $table->json('json_ld')->nullable()->after('date_modified');
            // The aggregate this page presents (Offer / Connection / a pillar entity).
            // Nullable — static pages and hubs own no aggregate.
            $table->nullableMorphs('pageable');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropMorphs('pageable');
            $table->dropColumn([
                'title',
                'meta_description',
                'canonical_path',
                'og_type',
                'og_image_path',
                'noindex',
                'date_published',
                'date_modified',
                'json_ld',
            ]);
        });
    }
};
