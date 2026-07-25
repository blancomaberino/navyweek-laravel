<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Research = one sourced brief per (versioned) research run — the durable, auditable
 * truth every published discount page derives from. The fourth of the four
 * lifecycles (Connection → Offer → Page → Research).
 *
 * `raw_markdown` always stores the full brief verbatim (zero data loss); the parsed
 * columns (`executive_summary`, `verified_facts`, …) are populated by the
 * ResearchBriefParser at import. Re-running research inserts a NEW row (version++)
 * and marks the prior one `superseded`. Per the build-clock rule only `last_verified`
 * traces to research — page dates never do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->constrained('connections')
                ->cascadeOnDelete();
            // Optional: a brief scoped to a specific offer rather than the brand.
            $table->foreignId('offer_id')
                ->nullable()
                ->constrained('offers')
                ->nullOnDelete();

            $table->string('brief_path')->nullable();
            // Zero-loss: the full brief markdown, always stored verbatim.
            $table->longText('raw_markdown');

            // Parsed structure (filled by ResearchBriefParser at import).
            $table->text('executive_summary')->nullable();
            $table->json('verified_facts')->nullable();
            $table->json('decision_table')->nullable();
            $table->json('maintenance')->nullable();
            $table->json('recommended_copy')->nullable();

            // high | medium | low
            $table->string('confidence_overall')->nullable();
            // Verification date IS a property of the brief; the review-due clock is
            // a Connection-lifecycle concern (connections.next_review_due, read by
            // ConnectionRepository::dueForReview) — not duplicated per brief.
            $table->date('last_verified')->nullable();
            // human | claude-pipeline
            $table->string('researched_by')->default('claude-pipeline');

            // Provenance: the primary skill + version that produced this brief.
            $table->string('skill_key')->nullable();
            $table->string('skill_version')->nullable();

            // draft | complete | stale | superseded
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Latest-brief-per-connection lookup (highest version for a connection).
            $table->index(['connection_id', 'version']);
            // Backs the offer_id nullOnDelete update + offer-scoped lookups
            // (explicit — Postgres FKs aren't auto-indexed).
            $table->index('offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research');
    }
};
