<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-audience savings rows for an offer (was the `tiers[]` array on the legacy
 * `Discount`). Normalized into individually orderable rows so the admin can edit
 * one row at a time and JSON-LD can enumerate them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')
                ->constrained('offers')
                ->cascadeOnDelete();
            // e.g. "Military (active, reserve, Guard, veterans, retirees)"
            $table->string('audience');
            // e.g. "20% off"
            $table->string('amount');
            // Optional qualifier, e.g. "Excludes limited-edition releases."
            $table->string('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Backs the ordered `where offer_id = ? order by sort_order` read
            // (Offer::tiers). Explicit because Postgres FKs are not auto-indexed.
            $table->index(['offer_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_tiers');
    }
};
