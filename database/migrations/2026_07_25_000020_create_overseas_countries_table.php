<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overseas host-country lookup — port of `bases/countries.ts`. Countries (and
 * country-equivalent U.S. territories, e.g. Guam) that host OCONUS installations;
 * the overseas base hubs (`/bases/<country>/`) group on it. `bases.country_slug`
 * is a soft slug FK here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overseas_countries', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('iso2', 2);
            // PACOM | EUCOM | CENTCOM | AFRICOM | SOUTHCOM — enum CombatantCommand.
            $table->string('region')->index();
            // A U.S. territory rendered with a country-equivalent hub (e.g. Guam).
            $table->boolean('is_us_territory')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overseas_countries');
    }
};
