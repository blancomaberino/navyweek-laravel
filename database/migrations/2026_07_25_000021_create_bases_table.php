<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naval bases — the first reference pillar (port of `bases/*.ts`, ~58 records).
 * `region_type` discriminates state-based (CONUS/Hawaii) from overseas
 * (country/territory) installations, deciding which column group applies. Cohesive
 * display-only lists (aka, major_units, key_facts, notable_events, nearby_bases)
 * are JSON; FAQs and sources attach via the shared polymorphic `faqs`/`sources`
 * tables (faqable/sourceable), not JSON columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bases', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->json('aka')->nullable();
            // Installation type — enum BaseType (hub grouping).
            $table->string('type')->index();
            // state | country | territory — enum RegionType (which columns apply).
            $table->string('region_type')->default('state')->index();

            // State-based fields (region_type = state). `state` is the us_states slug.
            $table->string('state')->nullable()->index();
            $table->string('state_name')->nullable();
            $table->string('state_abbr', 2)->nullable();

            // Overseas fields (region_type = country|territory).
            $table->string('country')->nullable();
            $table->string('country_slug')->nullable()->index();
            $table->string('country_iso2', 2)->nullable();
            // PACOM | EUCOM | … — enum CombatantCommand.
            $table->string('region')->nullable()->index();
            $table->string('host_nation')->nullable();
            $table->string('timezone')->nullable();
            $table->string('local_currency')->nullable();
            $table->json('local_language')->nullable();
            $table->string('sofa_status')->nullable();
            $table->boolean('command_sponsorship_required')->nullable();
            $table->boolean('passport_required')->nullable();

            // Geography / facts.
            $table->string('city');
            $table->string('county')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->integer('established');
            $table->string('personnel_count')->nullable();
            $table->string('area_acres')->nullable();
            $table->json('major_units');
            $table->json('key_facts');

            // SEO scalars.
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('h1');
            $table->string('hero_tagline');
            $table->string('seo_keyword_primary');

            // Optional identity / provenance.
            $table->string('commanding_officer')->nullable();
            $table->string('motto')->nullable();
            $table->string('nickname')->nullable();
            $table->string('wikipedia_url')->nullable();
            $table->string('official_url')->nullable();
            $table->json('notable_events')->nullable();
            // Slugs of related bases (display cross-links) — self-reference by slug.
            $table->json('nearby_bases')->nullable();
            // Soft slug link to fleet_weeks (that pillar lands in a later slice).
            $table->string('nearest_fleet_week_slug')->nullable();

            // Long-form body.
            $table->longText('overview');
            $table->longText('history');
            $table->text('location_context')->nullable();
            $table->text('host_nation_context')->nullable();

            // The base's own "last updated" label (distinct from a page's build clock).
            $table->date('last_updated');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bases');
    }
};
