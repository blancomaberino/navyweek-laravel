<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry of the research/QA skills that produce briefs (e.g.
 * `military-discount-research`, `seo-geo`). "Research is only as trustworthy as
 * the skill that produced it" — a skill update is a first-class re-research
 * trigger: a scheduled DetectSkillUpdatesAction re-hashes each installed skill and
 * bumps `current_version` when `content_hash` changes, flagging every Connection
 * whose latest research used an older version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            // Semver or monotonic — stored as a string to allow either.
            $table->string('current_version')->default('1');
            // Hash of the skill's SKILL.md + references/*; change ⇒ version bump.
            $table->string('content_hash')->nullable();
            $table->string('source_ref')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
