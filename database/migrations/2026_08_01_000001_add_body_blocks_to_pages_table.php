<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMS-editable page body: an ordered list of typed content blocks (heading, paragraph,
 * list, note) that a non-technical editor manages in Filament and the `pages.content`
 * view renders. Backs the DB-driven content pages (veterans-day, va-disability,
 * veterans-home-care, privacy/terms/contact) — the editorial prose that is NOT derived
 * from a data registry. Nullable: only content pages carry it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->json('body_blocks')->nullable()->after('json_ld');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('body_blocks');
        });
    }
};
