<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Air-show event guides (port of `airshows/*.ts` `AirShow`). Each row is a full
 * single-event visitor guide at `/air-show/<slug>/`. `published` gates whether
 * the page is live; `date_unconfirmed` (allowing empty start/end) suppresses the
 * Event JSON-LD; `canonical_override` marks a disambiguation/router page that
 * canonicalizes to another guide. Block-based body (`sections`), schema inputs
 * (location/offer/organizer), and list fields are JSON; FAQs and sources attach
 * via the shared polymorphic tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('air_shows', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('short_name');
            $table->string('name');
            $table->string('city');
            $table->string('state');
            $table->string('state_name');
            $table->unsignedSmallInteger('year');

            $table->string('base');
            $table->string('dates_label');
            // ISO dates; empty string allowed only when date_unconfirmed.
            $table->string('start_date');
            $table->string('end_date');
            $table->boolean('date_unconfirmed')->default(false);
            $table->string('gate_time')->nullable();
            // FREE | TICKETED — enum Admission.
            $table->string('admission');
            $table->string('parking')->nullable();
            $table->string('headliner');
            $table->json('performers'); // string[] (expected lineup)
            $table->string('official_url');
            $table->string('phone')->nullable();

            // scheduled | cancelled | postponed — enum AirShowStatus.
            $table->string('status')->index();
            $table->boolean('published')->default(false)->index();
            $table->json('needs_verification'); // editorial workflow, never rendered

            // Hero + body.
            $table->string('hero_headline');
            $table->json('intro');
            $table->json('quick_facts');
            $table->json('sections'); // AirShowSection[] {heading, blocks[]}
            $table->json('related_paragraph'); // AirShowRelatedLink[]
            $table->text('card_summary');
            $table->json('email_cta')->nullable();

            // Schema source fields.
            $table->string('schema_name');
            $table->text('event_description');
            $table->json('location'); // AirShowPlace
            $table->json('offer'); // AirShowOffer
            $table->json('organizer'); // AirShowOrganizer

            // SEO.
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('h1');
            $table->string('og_image');
            // When set, canonicalize to this path and suppress own Event JSON-LD.
            $table->string('canonical_override')->nullable();

            // Dates.
            $table->date('date_published');
            $table->date('date_modified');
            $table->string('last_verified');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('air_shows');
    }
};
