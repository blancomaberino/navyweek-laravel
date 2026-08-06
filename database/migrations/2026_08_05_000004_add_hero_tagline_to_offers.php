<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one-line tagline the discount guide shows under its h1. Distinct from
 * `discount_summary` (a longer sentence used elsewhere) — the live guide renders
 * the tagline here, so using the summary put different copy on all 981 pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->text('hero_tagline')->nullable()->after('discount_summary');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->dropColumn('hero_tagline');
        });
    }
};
