<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numbered redemption steps for an offer (was `redeemOnline[]` / `redeemInStore[]`
 * on the legacy `Discount`). Merged into one table discriminated by `channel`,
 * individually orderable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemption_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')
                ->constrained('offers')
                ->cascadeOnDelete();
            // online | in_store
            $table->string('channel')->default('online');
            $table->string('title');
            $table->text('detail');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Backs the ordered `where offer_id = ? order by sort_order` read
            // (Offer::redemptionSteps). Explicit because Postgres FKs aren't indexed.
            $table->index(['offer_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemption_steps');
    }
};
