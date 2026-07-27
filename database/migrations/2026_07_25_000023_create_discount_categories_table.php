<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discount CATEGORY hubs (port of `discounts/categories.ts`). A hub at
 * `/discount/<slug>/` lists every Connection whose `category` equals this row's
 * `match_category`, as a minimal brand directory. Ordering overrides (`pinned`,
 * `excluded`, `order`) are soft slug lists resolved against the connection
 * registry at read time (see EloquentDiscountCategoryRepository::orderedConnections),
 * so they carry no DB constraint. `intro` is the multi-paragraph lead (one <p>
 * per element).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // The Connection.category value this hub groups on, e.g. "Car Rental".
            // No index: hubs are fetched by `slug`; `match_category` filters the
            // connections table (indexed there), never this one.
            $table->string('match_category');

            // SEO.
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('h1');
            $table->text('hero_tagline');
            // Lead paragraphs — one <p> per array element.
            $table->json('intro');
            $table->string('og_image');

            // Ordering overrides — soft slug lists of Connection slugs.
            $table->json('pinned')->nullable();
            $table->json('excluded')->nullable();
            $table->json('order')->nullable();

            // Dates — build-clock driven, same policy as national pages.
            $table->date('date_published');
            $table->date('date_modified');
            // Human "facts verified on" label, e.g. "June 21, 2026".
            $table->string('last_verified');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_categories');
    }
};
