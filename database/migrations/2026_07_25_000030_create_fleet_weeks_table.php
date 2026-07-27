<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleet Week city guides (port of `fleetweek/*.ts`). One row per city, rendered
 * by a single flexible block template — `has_official_fleet_week` / `has_air_show`
 * gate which blocks render (Tier-3 cities with no standing event set the former
 * false and omit the festival/air-show data). The block payloads (schedule,
 * air-show, parade, ship-tours, viewing spots, festival schema, past years) are
 * cohesive display JSON; FAQs and sources attach via the shared polymorphic
 * tables. Dates are build-clock driven, per the site's date policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_weeks', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('city');
            $table->string('state');
            $table->string('state_abbr', 2);
            $table->unsignedSmallInteger('year');
            $table->string('branding_name');
            // spring | summer | fall | winter — enum FleetWeekSeason.
            $table->string('season')->index();
            $table->string('month_label');

            // Flex flags — drive which blocks render.
            $table->boolean('has_official_fleet_week')->default(true);
            $table->boolean('has_air_show')->default(false);

            // Block 1 — hero + status.
            // confirmed | scheduled | cancelled | rescheduled | off-season | none.
            $table->string('status')->index();
            // Editorial per-city status text — may carry specifics beyond the
            // generic FleetWeekStatus::label() (e.g. "Off-season — next event Oct
            // 2027"), so it is a stored column, not derived from the enum.
            $table->string('status_label');
            $table->string('status_note')->nullable();
            $table->string('festival_dates_label')->nullable();
            $table->string('airshow_dates_label')->nullable();
            $table->text('dek');
            $table->json('intro'); // one <p> per element

            // Block 2 — quick facts.
            $table->json('quick_facts');
            $table->string('official_url')->nullable();
            $table->string('official_site_label')->nullable();

            // Block 3 — schedule.
            $table->json('schedule'); // ScheduleRow[] {date, day, event, time, location}
            $table->string('schedule_note')->nullable();

            // Blocks 4–6 (optional) — air show, parade of ships, ship tours.
            $table->json('airshow')->nullable();
            $table->json('parade_of_ships')->nullable();
            $table->json('ship_tours')->nullable();

            // Block 7 — best viewing locations.
            $table->string('viewing_intro')->nullable();
            $table->json('viewing_spots'); // ViewingSpot[] {name, why, transit?, lat?, lng?}

            // Blocks 8–9 — getting there, history.
            $table->json('getting_there');
            $table->json('history');

            // Block 12 (optional) — what changed / past years.
            $table->json('past_years')->nullable();

            // Festival schema (official, dated cities only).
            $table->json('festival')->nullable();

            // Hub card / cross-link copy.
            $table->text('card_summary');
            $table->json('related_slugs')->nullable();

            // SEO.
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('h1');
            $table->string('primary_keyword');
            $table->string('og_image');

            // Dates — build-clock driven.
            $table->date('date_published');
            $table->date('date_modified');
            $table->string('last_verified');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_weeks');
    }
};
