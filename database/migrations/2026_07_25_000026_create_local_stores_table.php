<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A physical storefront for a `local_discount` (port of the legacy `LocalStore`,
 * the `locations[]` array). Most local businesses have one; chains list several
 * and the first (`sort_order` 0) is the primary that drives the NAP block +
 * LocalBusiness schema. Opening hours are the child `local_store_hours` rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_discount_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('street');
            $table->string('city');
            $table->string('state_abbr', 2);
            $table->string('zip');
            $table->string('phone')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            // Pre-built Google Maps directions link; falls back to a geo query.
            $table->string('directions_url')->nullable();
            // Keyless Google Maps embed URL (…&output=embed) for the NAP-card map.
            $table->string('map_embed_url')->nullable();
            // Approximate straight-line distance from the metro anchor, for sorting.
            $table->string('distance_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_stores');
    }
};
