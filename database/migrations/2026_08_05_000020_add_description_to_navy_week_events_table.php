<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The city page's "Mission Dossier" prose — the two-to-three editorial paragraphs
 * the legacy `getCityDescription()` (src/data/data.ts) held in a hardcoded map.
 * It is per-city copy, not a derived value, so it belongs on the row like every
 * other city-detail field. Stored as a JSON list of paragraphs to match the
 * legacy shape (and `military_context` next to it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navy_week_events', function (Blueprint $table) {
            $table->json('description')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('navy_week_events', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
