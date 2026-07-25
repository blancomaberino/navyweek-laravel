<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Navy ranks, paygrades, designators and ratings — the second reference pillar
 * (port of `ranks/*.ts`, ~156 records). Single-table inheritance: `category` is
 * the discriminator over the legacy `NavyRankEntry` union; common columns apply to
 * every row, the variant column groups below are nullable and populated only for
 * their category. Nested arrays are JSON; FAQs/sources attach via the shared
 * polymorphic tables. The legacy `next_rank_slug` (officers) and `next_paygrade_slug`
 * (enlisted) are the same linked-list concept, unified here as `next_slug` /
 * `previous_slug`. `community` collides between designators and ratings (different
 * vocabularies), so it is split into `designator_community` / `rating_community`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            // The STI discriminator — enum RankCategory.
            $table->string('category')->index();

            // ---- Common (RankCommon) ----
            $table->string('name');
            $table->string('abbreviation');
            $table->string('paygrade');
            $table->string('insignia_path');
            $table->string('insignia_alt');
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('h1');
            $table->string('hero_tagline');
            $table->longText('overview');
            $table->longText('history');
            $table->json('responsibilities');
            $table->string('addressing');
            $table->json('prerequisites');
            $table->json('common_assignments');
            // {min_usd_monthly, max_usd_monthly, pay_data_year, note?}
            $table->json('pay_range')->nullable();
            // Single soft slug FK → bases (the entry's primary related base).
            $table->string('related_base_slug')->nullable();
            $table->string('related_base_note')->nullable();
            $table->date('last_updated');

            // ---- Officer (commissioned / warrant) + enlisted linked list ----
            // Required for officers + enlisted, optional for designators, absent for ratings.
            $table->string('nato_code')->nullable();
            // Unified next_rank_slug (officer) / next_paygrade_slug (enlisted) — self-ref.
            $table->string('next_slug')->nullable();
            $table->string('previous_slug')->nullable();
            $table->boolean('is_flag_officer')->nullable();     // officer-commissioned
            $table->boolean('is_chief')->nullable();            // enlisted-paygrade
            $table->json('community_variants')->nullable();     // enlisted-paygrade
            $table->json('special_billets')->nullable();        // enlisted-paygrade

            // ---- Officer designator ----
            $table->string('designator_code')->nullable();
            // enum DesignatorCommunity (url | restricted-line | staff-corps).
            $table->string('designator_community')->nullable();
            $table->json('commissioning_sources')->nullable();
            $table->json('related_designators')->nullable();    // self-ref slugs
            $table->string('device_description')->nullable();

            // ---- Rating (active / historical) ----
            // enum RatingCommunity (general | aviation | … | admin).
            $table->string('rating_community')->nullable();
            $table->text('what_they_do')->nullable();
            $table->string('asvab_requirement')->nullable();
            $table->integer('asvab_score_min')->nullable();
            $table->string('a_school_location')->nullable();
            $table->string('a_school_location_slug')->nullable();  // soft slug FK → bases
            $table->string('a_school_duration')->nullable();
            $table->string('clearance_required')->nullable();
            $table->integer('enlistment_obligation_years')->nullable();
            $table->json('typical_platforms')->nullable();
            $table->json('related_ratings')->nullable();        // self-ref slugs
            $table->json('nec_examples')->nullable();
            $table->string('badge_description')->nullable();

            // ---- Shared by designator + rating (same shape, disjoint categories) ----
            $table->json('predecessor_ratings')->nullable();    // self-ref slugs
            $table->json('related_base_slugs')->nullable();     // soft slugs → bases
            // DesignatorTrainingStop[] / RatingTrainingStop[].
            $table->json('training_pipeline')->nullable();
            // DesignatorCareerMilestone[] / RatingCareerMilestone[] — legacy
            // `typical_career_path` (designator) and `career_path` (rating) unified.
            $table->json('career_path')->nullable();

            // ---- Rating historical ----
            $table->string('active_period')->nullable();
            $table->string('years_active')->nullable();
            $table->integer('decommissioned_year')->nullable();
            $table->string('decommission_reason')->nullable();
            $table->json('successor_ratings')->nullable();      // self-ref slugs
            $table->json('notable_for')->nullable();
            // enum HistoricRatingEra[] (era tags).
            $table->json('era_tags')->nullable();
            $table->string('merged_into_slug')->nullable();     // self-ref slug

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
