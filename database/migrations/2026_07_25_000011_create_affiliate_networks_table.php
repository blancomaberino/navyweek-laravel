<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate-network registry (port of `NETWORK_SUBID_REGISTRY` in the legacy
 * `networks.ts`). Each network declares the query parameter that carries a
 * placement sub-ID on outbound links, plus any extra params always sent
 * alongside it. `direct` = no network → UTM fallback. Seeded by
 * AffiliateNetworkSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_networks', function (Blueprint $table) {
            $table->id();
            // direct | impact | cj | awin | rakuten | avantlink | amazon
            $table->string('key')->unique();
            $table->string('name');
            // The query param that carries the placement sub-ID, e.g. "subId1".
            $table->string('subid_param');
            // Extra params always appended (direct uses this for utm_source/medium).
            $table->json('extra_params')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_networks');
    }
};
