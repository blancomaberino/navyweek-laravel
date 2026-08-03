<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public `/authors/{slug}/` profile page needs two more editorial-profile
 * fields beyond the compact byline set: the long-form `bio` prose (the paragraph
 * the legacy hardcoded author pages carried) and a `linkedin_url` external identity
 * link (schema.org Person.sameAs + the profile's "Connect" section). Both nullable
 * and public — an account with no byline leaves them empty. Assignable from the
 * admin panel alongside the existing profile columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Person.description on the author profile page — the long-form bio prose
            // (the shorter `credentials` line stays the byline citation).
            $table->text('bio')->nullable()->after('knows_about');
            // Person.sameAs — the author's public LinkedIn profile, linked from the
            // profile page's "Connect" section.
            $table->string('linkedin_url')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'linkedin_url']);
        });
    }
};
