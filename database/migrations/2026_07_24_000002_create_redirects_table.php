<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The redirect store. Subsumes every hand-coded 301 in the legacy `middleware.ts`
 * (seeded as rows) and is the sink for editor URL changes (auto-301 on slug change,
 * Phase 4). `match_type` = exact | prefix; the two algorithmic prefix collapses
 * (/navy-ranks/**, /navy-ratings/**) use `prefix`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status')->default(301);
            // slug-change | manual | retirement | import-legacy
            $table->string('reason')->default('manual');
            // exact | prefix
            $table->string('match_type')->default('exact')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
