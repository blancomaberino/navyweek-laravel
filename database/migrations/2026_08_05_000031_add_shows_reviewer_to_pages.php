<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not every page names a reviewer. `VeteransDay.tsx` renders an author-only
 * byline, where our port showed the default reviewer row and ran 112px tall.
 *
 * This is a flag rather than a null `reviewer_id` because `EditorialTeamSeeder`
 * back-fills the default byline onto any page whose reviewer is null — so
 * clearing the id would silently come back on the next seed. The flag records the
 * editorial intent ("this page is author-only") in a way re-seeding cannot undo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->boolean('shows_reviewer')->default(true)->after('shows_process_link');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('shows_reviewer');
        });
    }
};
