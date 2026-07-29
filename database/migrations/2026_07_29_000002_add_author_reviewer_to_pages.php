<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A page's editorial byline: the `author` who wrote it and the `reviewer` who
 * verified it, both `users` rows and both assignable from the admin panel. The
 * discount-guide JSON-LD reads its Article `author` + WebPage `reviewedBy` Person
 * nodes from these — replacing the legacy hardcoded persons. Nullable + `nullOnDelete`
 * so deleting a staff account never deletes their pages (the byline just clears).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('author_id')
                ->nullable()
                ->after('json_ld')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('reviewer_id')
                ->nullable()
                ->after('author_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
            $table->dropConstrainedForeignId('reviewer_id');
        });
    }
};
