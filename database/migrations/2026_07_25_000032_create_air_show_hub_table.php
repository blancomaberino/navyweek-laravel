<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The air-show directory hub (port of `airshows/types.ts` `AirShowHubMeta`) — the
 * `/air-show/` landing page that lists the event guides. A single editable content
 * row (a table, not config, so Filament can edit it); `basePath` is the natural
 * key. FAQs attach via the shared polymorphic `faqs` table (feeds the hub's
 * FAQPage schema); the other copy blocks are JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('air_show_hub', function (Blueprint $table) {
            $table->id();
            $table->string('base_path')->unique(); // e.g. "/air-show"
            $table->unsignedSmallInteger('year');
            $table->string('eyebrow');
            $table->string('hub_title');
            $table->string('hub_subtitle');
            $table->string('seo_headline');
            $table->json('intro');
            $table->json('key_facts');
            $table->json('about');
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('og_image');
            $table->date('date_published');
            $table->date('date_modified');
            $table->string('last_verified');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('air_show_hub');
    }
};
