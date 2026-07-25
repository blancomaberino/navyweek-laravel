<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audience lookup — the eligible-cohort vocabulary (military, veteran, student, …)
 * an Offer can target. Promotes the legacy 9 `DiscountAudience` booleans (which the
 * `Audience` enum consolidated to 7 cases) into first-class rows so the admin can filter
 * ("all SheerID + first-responder offers") and JSON-LD can enumerate them.
 *
 * `key` mirrors the `App\Domain\Crm\Enums\Audience` value; the enum stays the
 * canonical vocabulary and seed source (AudienceSeeder), the table is the joinable
 * form used by the `offer_audience` pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audiences', function (Blueprint $table) {
            $table->id();
            // Matches the Audience enum backing value (military, veteran, …).
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audiences');
    }
};
