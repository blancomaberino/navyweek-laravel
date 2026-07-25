<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot linking Offers to the audiences they serve (many-to-many). Replaces the 9
 * boolean audience columns on the legacy flat `Discount` — a normalized join so an
 * offer can carry any set of cohorts and both directions ("offer's audiences" /
 * "offers for this audience") are indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_audience', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')
                ->constrained('offers')
                ->cascadeOnDelete();
            $table->foreignId('audience_id')
                ->constrained('audiences')
                ->cascadeOnDelete();
            $table->timestamps();

            // One row per (offer, audience); also serves the offer's-audiences read.
            $table->unique(['offer_id', 'audience_id']);
            // Reverse lookup (all offers for an audience) — Postgres FKs aren't
            // auto-indexed, so index the second column explicitly.
            $table->index('audience_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_audience');
    }
};
