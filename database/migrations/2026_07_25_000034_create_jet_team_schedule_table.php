<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every stop on a team's official season tour (port of `JetTeamScheduleRow`) —
 * these power the hub schedule table. A row is factual schedule data and does
 * NOT imply a published city guide exists (the `slug` links only when a guide is
 * published). A city can appear twice in one season under the same slug (e.g.
 * Pensacola Beach in July + November), so `slug` is NOT unique here; `sort_order`
 * preserves the authored tour order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jet_team_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jet_team_id')->constrained()->cascadeOnDelete();

            $table->string('dates_label');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('city');
            $table->string('state', 2);
            $table->string('show');
            $table->string('venue')->nullable();
            // FREE | TICKETED — enum Admission (omitted when not applicable).
            $table->string('admission')->nullable();
            // scheduled | cancelled | postponed | completed — enum JetTeamStatus.
            $table->string('status')->default('scheduled');
            // City-guide slug — links only if a published guide exists (not unique).
            $table->string('slug');
            $table->string('guide_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jet_team_schedule');
    }
};
