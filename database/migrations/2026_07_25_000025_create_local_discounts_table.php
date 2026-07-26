<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local-business discount pages (port of `localDiscounts/*.ts`) — a distinct page
 * type from the national brand guides, organized geographically at
 * `/discounts/<state>/<city>/<business_slug>/` and carrying physical-location data
 * (its `local_stores` + `local_store_hours` children and nearby bases). `state` is
 * a soft slug FK to the shared `us_states` lookup. The military `audience` is the
 * legacy fixed 5-flag struct, stored as booleans. Cohesive display lists (tiers,
 * redeem steps, exclusions, key facts, nearby bases, intro/details) are JSON;
 * FAQs and sources attach via the shared polymorphic `faqs`/`sources` tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_discounts', function (Blueprint $table) {
            $table->id();

            // Geographic identity — drives the URL and the rollup hubs. `state`
            // and `state`+`city` reads are served by the composite unique below
            // (leftmost-prefix), so no standalone index on either is needed.
            $table->string('state'); // us_states slug, e.g. 'texas'
            $table->string('state_name');
            $table->string('state_abbr', 2);
            $table->string('city'); // city slug, e.g. 'houston'
            $table->string('city_name');
            $table->string('business_slug');
            // One page per state/city/business (also serves forState/forCity reads).
            $table->unique(['state', 'city', 'business_slug']);

            // Identity.
            $table->string('company');
            $table->string('business_type'); // LocalBusiness additionalType hint
            $table->string('category')->index(); // editorial grouping, e.g. 'Attractions'
            $table->string('logo')->nullable();
            $table->string('logo_alt')->nullable();
            $table->string('logo_background')->nullable();

            $table->string('official_url');
            $table->string('brand_home_url');

            // The offer.
            $table->string('headline_discount');
            $table->text('discount_summary');
            // In-store ID | Reservation + ID | SheerID | ID.me | Other — enum LocalVerification.
            $table->string('verification');
            $table->string('verification_detail')->nullable();

            // Military audience — legacy fixed 5-flag struct.
            $table->boolean('active_duty')->default(false);
            $table->boolean('veterans')->default(false);
            $table->boolean('retirees')->default(false);
            $table->boolean('reserve_guard')->default(false);
            $table->boolean('military_family')->default(false);

            $table->json('eligibility');
            $table->json('tiers');          // LocalDiscountTier[] {audience, amount, note?}
            $table->json('redeem_in_store'); // LocalDiscountStep[] {title, detail}
            $table->json('exclusions');
            $table->json('nearby_bases');    // NearbyBase[] {name, branch?, distanceMi, note?}
            $table->string('service_area')->nullable();
            $table->string('price_range')->nullable(); // schema.org priceRange hint

            // Editorial.
            $table->json('intro');
            $table->json('details');
            $table->json('key_facts'); // LocalDiscountKeyFact[] {label, value}

            // SEO.
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('h1');
            $table->text('hero_tagline');
            $table->string('primary_keyword');
            $table->string('og_image')->nullable();

            // Dates — build-clock driven, same policy as national pages.
            $table->date('date_published');
            $table->date('date_modified');
            $table->string('last_verified');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_discounts');
    }
};
