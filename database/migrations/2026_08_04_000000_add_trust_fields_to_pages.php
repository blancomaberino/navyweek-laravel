<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill columns for the shared "trust" chrome the legacy reference pages carry
 * (ReferenceTrust.tsx / KeyFacts.tsx / NavyReferenceBackLink.tsx), so the CMS —
 * not a hardcoded Blade constant — is the source of truth for every one of them.
 *
 * - `h1` is DISTINCT from `title`: the legacy discount records carry both a
 *   `metaTitle` (the <title>) and a separate on-page `h1`. Nullable — a page with
 *   no explicit h1 falls back to `title` at render.
 * - `last_reviewed` / `sources_checked` drive the byline dates.
 * - `key_facts` holds the KeyFacts block: {title, facts:[{label,value}], source:{label,url}}.
 * - `editorial_*` + `trust_page_label` drive the "Editorial policy" box and the
 *   "Report an outdated fact" mailto subject.
 * - `shows_reference_backlink` toggles the "← Navy Reference" link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('h1')->nullable()->after('title');
            $table->date('last_reviewed')->nullable()->after('date_modified');
            $table->date('sources_checked')->nullable()->after('last_reviewed');
            $table->json('key_facts')->nullable()->after('body_blocks');
            $table->text('editorial_source_priority')->nullable()->after('key_facts');
            $table->text('editorial_review_cadence')->nullable()->after('editorial_source_priority');
            $table->string('trust_page_label')->nullable()->after('editorial_review_cadence');
            $table->boolean('shows_reference_backlink')->default(false)->after('trust_page_label');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn([
                'h1',
                'last_reviewed',
                'sources_checked',
                'key_facts',
                'editorial_source_priority',
                'editorial_review_cadence',
                'trust_page_label',
                'shows_reference_backlink',
            ]);
        });
    }
};
