<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared, polymorphic citation store. A `source` is a primary-source reference
 * (the URL a fact was verified against) attached via `sourceable` to whatever it
 * substantiates — an Offer's key fact, a Research brief, or a Page. Centralizing
 * citations here backs the "every factual claim traces to a verified primary
 * source" (YMYL) invariant across aggregates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            // sourceable_type + sourceable_id (+ composite index) — Offer/Research/Page.
            $table->morphs('sourceable');
            // Human label, e.g. "Nike Military & First Responder Discount page".
            $table->string('label');
            $table->string('url');
            $table->string('publisher')->nullable();
            // primary | secondary | official | press | community — enum SourceType.
            $table->string('source_type')->nullable();
            $table->date('accessed_at')->nullable();
            // high | medium | low — enum ConfidenceLevel (Shared).
            $table->string('confidence')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
