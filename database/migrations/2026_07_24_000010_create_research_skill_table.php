<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records every skill (and the version) that contributed to a research brief — e.g.
 * `military-discount-research` for the facts + `seo-geo` for the citability pass.
 * A Connection's "skills used" view is derived from its research history through
 * this pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')
                ->constrained('research')
                ->cascadeOnDelete();
            $table->foreignId('skill_id')
                ->constrained('skills')
                ->cascadeOnDelete();
            // The skill version in effect when this brief was produced.
            $table->string('skill_version');
            // What the skill contributed, e.g. "facts" or "citability".
            $table->string('used_for')->nullable();
            $table->timestamps();

            $table->unique(['research_id', 'skill_id', 'used_for']);
            // The unique index is research_id-leftmost, so it can't serve the
            // skill_id side (Skill::research(), cascade delete). Index it explicitly.
            $table->index('skill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_skill');
    }
};
