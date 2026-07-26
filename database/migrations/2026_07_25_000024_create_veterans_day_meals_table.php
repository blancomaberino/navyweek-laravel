<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veterans Day free-meal roundup (port of `veterans-day-meals/*.ts`). Each row is
 * one brand's free-meal offer for Nov 11. YMYL gate: a row renders ONLY when
 * `status = verified` AND it carries a primary `source_url` — enforced in the
 * repository's verified() read, mirroring the legacy render gate. `discount_slug`
 * is a soft FK to a Connection slug (the brand's `/discount/` guide); when unset
 * or unresolved the brand cell simply has no internal link (a backlog item, not
 * an error). `eligibility` is an enum-list (MealEligibility).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterans_day_meals', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('brand');
            // Soft FK → connections.slug (the brand's /discount/ guide). No constraint.
            $table->string('discount_slug')->nullable()->index();

            $table->string('offer');
            // MealEligibility[] — who qualifies, per the source.
            $table->json('eligibility');
            // True ONLY if the source explicitly states dependents/family qualify.
            $table->boolean('dependents_eligible')->default(false);
            // dine-in | takeout | both — enum MealRedemption.
            $table->string('redemption');
            $table->string('proof_required');
            // ISO single day or a human range when multi-day.
            $table->string('offer_date');
            // true = all locations; false = participating locations only.
            $table->boolean('nationwide')->default(false);

            // Primary source — REQUIRED for an offer to render.
            $table->string('source_url');
            $table->string('source_label');
            // ISO date last verified against source — drives the "Verified" badge.
            $table->date('last_verified_at');
            // verified | pending | discontinued — enum MealStatus (render gate).
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veterans_day_meals');
    }
};
