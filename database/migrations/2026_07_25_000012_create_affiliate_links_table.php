<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound offer links, tagged with a placement sub-ID at render time (port of
 * the `withPlacement` inputs in the legacy `links.ts`). A link belongs to a
 * connection and/or an offer, uses one affiliate network, and lives in a fixed
 * on-page placement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->nullable()
                ->constrained('connections')
                ->cascadeOnDelete();
            $table->foreignId('offer_id')
                ->nullable()
                ->constrained('offers')
                ->cascadeOnDelete();
            $table->foreignId('affiliate_network_id')
                ->constrained('affiliate_networks')
                ->cascadeOnDelete();

            $table->string('base_url');
            // hero-cta | sticky-footer | keyfacts-source
            $table->string('placement');
            // SEO-safe rel for monetized links (Google link-spam guidance).
            $table->string('rel')->default('sponsored noopener noreferrer');
            $table->timestamps();

            // Explicit — Postgres FKs aren't auto-indexed; these back the
            // per-connection / per-offer link reads.
            $table->index('connection_id');
            $table->index('offer_id');
            $table->index('affiliate_network_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_links');
    }
};
