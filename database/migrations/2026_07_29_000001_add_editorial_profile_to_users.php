<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users double as the editorial byline. A page's author + reviewer are `users`
 * rows, assignable from the admin panel — so the E-E-A-T `Person` JSON-LD the
 * legacy discount pages hardcoded is now data, not a code constant. These columns
 * carry only the *public* editorial profile (nothing private): the `/authors/{slug}/`
 * profile slug, job title, credentials/bio line, avatar, and expertise list. All
 * nullable — a login account with no byline (e.g. an ops admin) leaves them empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Public author-profile slug → the `/authors/{slug}/` URL + Person @id.
            $table->string('slug')->nullable()->unique()->after('email');
            // schema.org Person.jobTitle (e.g. "Editor, NavyWeek.org").
            $table->string('job_title')->nullable()->after('slug');
            // Person.description — the credentials/bio line the pages cite.
            $table->text('credentials')->nullable()->after('job_title');
            // Person.image — site-relative avatar path (prefixed with the host at render).
            $table->string('avatar_path')->nullable()->after('credentials');
            // Person.knowsAbout — the author's expertise topics.
            $table->json('knows_about')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['slug', 'job_title', 'credentials', 'avatar_path', 'knows_about']);
        });
    }
};
