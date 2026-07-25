<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offers = the discount(s) a Connection carries. The second of the four lifecycles
 * the legacy flat `Discount` record is split into — and the normalization the old
 * model could not do: one brand → many offers (everyday, promo, membership, an
 * advisory "no discount" note, …).
 *
 * The offer facts (headline, summary, verification, audience) and the cohesive
 * page-scoped display units (eligibility, exclusions, key_facts, promo, the
 * "best savings path" tools, share CTA) live here. Per-audience savings rows and
 * redemption steps are normalized into their own tables (`offer_tiers`,
 * `redemption_steps`). Page/SEO/date columns do NOT live here — they belong to
 * the `pages` layer (build clock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->constrained('connections')
                ->cascadeOnDelete();

            // CRM-facing label to tell an offer apart in the admin.
            $table->string('internal_label')->nullable();
            // everyday | promo | advisory_no_discount | membership | other
            $table->string('offer_type')->default('everyday')->index();

            // Headline facts.
            $table->string('headline_discount')->nullable();
            $table->text('discount_summary')->nullable();
            // ID.me | GovX | SheerID | VerifyPass | In-store ID | Other
            $table->string('verification')->nullable();
            $table->string('verification_url')->nullable();
            // Falls back to the connection's official_url when null.
            $table->string('official_url')->nullable();
            $table->string('audience_label')->nullable();

            // Cohesive, page-scoped, display-only JSON units (edited as a whole in
            // a Filament Repeater/Builder). Normalized rows live in child tables.
            $table->json('eligibility')->nullable();
            $table->json('exclusions')->nullable();
            $table->json('key_facts')->nullable();
            $table->json('promo')->nullable();
            $table->json('savings_hack')->nullable();
            $table->json('savings_table')->nullable();
            $table->json('savings_table_secondary')->nullable();
            $table->json('chooser')->nullable();
            $table->json('share_cta')->nullable();

            // Template copy overrides (advisory pages replace discount-assuming copy).
            $table->string('cta_label')->nullable();
            $table->string('cta_subnote')->nullable();
            $table->text('source_priority_note')->nullable();
            $table->string('sticky_cta_label')->nullable();

            // Ordering / publication. One primary offer per connection drives the
            // brand's main /discount/ page.
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false)->index();

            $table->timestamps();

            // Serves the primary-offer lookup and the "primary first, then sort"
            // read in EloquentOfferRepository::forConnection (fully index-ordered).
            $table->index(['connection_id', 'is_primary', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
