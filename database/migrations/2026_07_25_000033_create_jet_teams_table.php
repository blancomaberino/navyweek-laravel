<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The flight-demonstration squadrons (port of `jetteams/types.ts` `TeamMeta`) —
 * the hub (`/{team}/`) content + identity for each team (Blue Angels,
 * Thunderbirds). `team` is the natural key (enum TeamId); `base_path` is the
 * URL base looked up by `getTeamMetaByBasePath`. The season schedule and city
 * guides are the `jet_team_schedule` / `jet_team_cities` children. Hub FAQs
 * attach via the shared polymorphic `faqs` table; the `cross_team` footer link
 * and the copy blocks are JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jet_teams', function (Blueprint $table) {
            $table->id();
            // blue-angels | thunderbirds — enum TeamId (natural key).
            $table->string('team')->unique();
            $table->string('name');
            $table->string('full_name');
            $table->string('branch');
            $table->string('aircraft');
            $table->string('home_base');
            $table->string('base_path')->unique(); // e.g. "/blue-angels"
            $table->unsignedSmallInteger('year');

            // Hero.
            $table->string('eyebrow');
            $table->string('hub_title');
            $table->string('hub_subtitle');
            $table->string('seo_headline');
            $table->json('intro');

            // Quick facts / about.
            $table->json('key_facts');
            $table->json('about');

            // Cross-team footer link — JetTeamRelatedLink {before?, label, href, after?}.
            $table->json('cross_team');

            // SEO.
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('og_image');

            // Dates.
            $table->date('date_published');
            $table->date('date_modified');
            $table->string('last_verified');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jet_teams');
    }
};
