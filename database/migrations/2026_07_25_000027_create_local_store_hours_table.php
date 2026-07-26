<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One opening-hours span for a `local_store` (port of the legacy `OpeningHours`,
 * the store's `hours[]` array). `days` is the human label (e.g. "Mon–Sun");
 * `day_of_week` is the matching schema.org day-name list for the same span,
 * mapped to LocalBusiness openingHoursSpecification. `opens`/`closes` are 24h.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_store_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_store_id')->constrained()->cascadeOnDelete();

            $table->string('days'); // human label, e.g. "Mon–Sun"
            $table->json('day_of_week'); // schema.org day names for this span
            $table->string('opens'); // '09:00' (24h)
            $table->string('closes'); // '17:00' (24h)
            $table->string('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_store_hours');
    }
};
