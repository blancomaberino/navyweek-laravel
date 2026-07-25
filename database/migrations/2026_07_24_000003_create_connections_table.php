<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connections = brands in the CRM. The first of the four lifecycles the legacy
 * flat `Discount` record is split into (Connection → Offer → Page → Research).
 *
 * Columns map 1:1 to the legacy `pipeline/queue/*.json` brand shape (slug, brand,
 * key, status, priorityTier, maxVolume, totalVolume, keywordCount, minDifficulty,
 * cpc, audiences[], category, topKeyword, lastVerifiedAt, briefPath) plus the CRM
 * fields the rebuild adds: per-connection research cadence, next-review scheduling,
 * duplicate lineage, and brand-level URLs. Both the active queue (~1,739) and the
 * backlog (~13,637) import here — `is_backlog` distinguishes them.
 *
 * `category` is kept verbatim as the freeform industry label from the queue
 * (100+ distinct values, lossless); the ~15 category *hub* pages and their FK
 * land with the rendering/publishing slice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();

            // Identity (from the queue brand record).
            $table->string('slug')->unique();
            $table->string('brand');
            $table->string('key')->index();
            $table->string('category')->nullable();

            // CRM pipeline.
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('priority_tier')->nullable()->index();
            $table->boolean('is_backlog')->default(false)->index();

            // Keyword / search-volume metrics (Ahrefs-derived).
            $table->unsignedInteger('max_volume')->nullable()->index();
            $table->unsignedInteger('total_volume')->nullable();
            $table->unsignedInteger('keyword_count')->nullable();
            $table->unsignedSmallInteger('min_difficulty')->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->string('top_keyword')->nullable();
            $table->json('audiences')->nullable();

            // Research cadence (DB successor to the global 45-day staleness report).
            $table->unsignedSmallInteger('research_cadence_days')->default(45);
            $table->date('last_verified_at')->nullable();
            $table->date('next_review_due')->nullable()->index();

            // Duplicate lineage — a duplicate points at its canonical connection.
            $table->foreignId('duplicate_of')
                ->nullable()
                ->constrained('connections')
                ->nullOnDelete();

            // Brand-level URLs. (The default-affiliate-network FK lands with the
            // affiliate-networks slice, which adds the column + constraint together.)
            $table->string('brand_home_url')->nullable();
            $table->string('official_url')->nullable();
            $table->string('logo_url')->nullable();

            // Provenance.
            $table->string('brief_path')->nullable();
            $table->string('source_csv')->nullable();
            $table->string('added_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
