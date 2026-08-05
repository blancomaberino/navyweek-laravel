<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Byline credentials are per PAGE, not per user.
 *
 * The same reviewer renders three different strings on the live site: the VA
 * guides carry "Former U.S. Navy officer (U.S. Naval Academy '04, EOD) — reviews
 * for general accuracy and plain-language clarity; not a VA-accredited
 * representative", the credit-cards guide carries the short default, and
 * /our-process/ carries "Former U.S. Navy EOD officer". The first of those is a
 * YMYL disclaimer, not a bio — storing only `users.credentials` published a
 * weaker one on exactly the pages that need it most.
 *
 * Both columns are nullable and fall back to the user's own credentials, so only
 * a page that genuinely differs carries a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->text('author_credentials')->nullable()->after('reviewer_id');
            $table->text('reviewer_credentials')->nullable()->after('author_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['author_credentials', 'reviewer_credentials']);
        });
    }
};
