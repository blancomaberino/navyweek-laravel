<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Navy Week stops (port of `data.ts` `NavyWeekEvent` + `CityData` + the
 * `cityExtras.ts` `CityExtras`). The legacy split across three files was a
 * file-organization artifact — all three describe one city's Navy Week, keyed by
 * slug — so they fold into one row here. `sequence` preserves the legacy numeric
 * `id` (the canonical 1..N ordering). The rich city-detail block is optional
 * (nullable): the core stop always exists, the venues/schedule/context detail is
 * filled in per city. Cohesive display lists (venues, daily_schedule, navy_assets,
 * …) are JSON; FAQs and official sources attach via the shared polymorphic tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navy_week_events', function (Blueprint $table) {
            $table->id();
            // Legacy numeric id — the canonical stop ordering (1..N).
            $table->unsignedInteger('sequence')->unique();
            $table->string('slug')->unique();
            $table->string('city');
            $table->string('state');
            $table->string('state_abbr', 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('anchor_event');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->boolean('first_time')->default(false);
            // First-time *location* that is not a full first-time host city.
            $table->boolean('first_time_location')->nullable();
            // Custom badge text shown in place of the generic "First Time Host" badge.
            $table->string('first_time_badge')->nullable();
            // completed | active | upcoming — enum NavyWeekStatus.
            $table->string('status')->default('upcoming')->index();

            // ---- City detail (CityData + CityExtras) — all optional ----
            $table->text('anchor_event_detail')->nullable();
            $table->string('anchor_event_url')->nullable();
            $table->text('first_time_note')->nullable();
            $table->json('navy_assets')->nullable();
            $table->json('key_venues')->nullable();
            $table->json('military_context')->nullable();
            $table->string('navco_url')->nullable();
            $table->json('highlights')->nullable();
            // Venue[] {name, address?, lat?, lng?, notes?, parking?, source_level?}.
            $table->json('venues')->nullable();
            // DailyScheduleDay[] {date, tba?, items[{time?,title,venue?,description?,source?,source_level?}]}.
            $table->json('daily_schedule')->nullable();
            $table->text('parking_notes')->nullable();
            $table->text('cost_summary')->nullable();
            // ISO date the city detail was last verified against its sources.
            $table->date('last_verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navy_week_events');
    }
};
