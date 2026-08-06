<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The brand logo chip on each discount guide sits on a per-brand background
 * colour (dark logos need a light chip and vice versa). Present in the legacy
 * records as `logoBackground` but never imported, so every chip rendered white.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table): void {
            $table->string('logo_background', 32)->nullable()->after('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table): void {
            $table->dropColumn('logo_background');
        });
    }
};
