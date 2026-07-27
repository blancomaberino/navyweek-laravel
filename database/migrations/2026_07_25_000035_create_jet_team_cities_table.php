<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Published, full jet-team city guides (port of `JetTeamCity`). Only these get a
 * route (`/{team}/{slug}/`), prerender, sitemap entry, OG image, and an inbound
 * hub link — `published` is a property of the record, not a separate list. One
 * guide per team+slug. A city the team visits twice in a season carries the
 * optional `second_*` window. FAQs and sources reuse the shared polymorphic
 * tables; the body blocks and workflow lists are JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jet_team_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jet_team_id')->constrained()->cascadeOnDelete();

            $table->string('slug');
            // One guide per team + slug (the /{team}/{slug}/ route).
            $table->unique(['jet_team_id', 'slug']);
            $table->string('city');
            $table->string('state', 2);
            $table->string('state_name');
            $table->unsignedSmallInteger('year');

            $table->string('show');
            $table->string('venue');
            // FREE | TICKETED — enum Admission.
            $table->string('admission');
            $table->string('dates_label');
            $table->date('start_date');
            $table->date('end_date');

            // Optional second appearance in the same season.
            $table->string('second_dates_label')->nullable();
            $table->date('second_start_date')->nullable();
            $table->date('second_end_date')->nullable();

            // scheduled | cancelled | postponed | completed — enum JetTeamStatus.
            $table->string('status')->index();
            $table->boolean('published')->default(false)->index();
            $table->json('needs_verification'); // editorial workflow, never rendered

            // Hero + body.
            $table->string('hero_dateline');
            $table->string('dek')->nullable();
            $table->json('intro');
            $table->json('quick_facts');
            $table->json('sections'); // JetTeamSection[] {heading, paragraphs?, bullets?}
            $table->json('related_paragraph'); // JetTeamRelatedLink[]
            $table->text('card_summary');

            // SEO.
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('h1');
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
        Schema::dropIfExists('jet_team_cities');
    }
};
