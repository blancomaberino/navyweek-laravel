<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last two pieces of the legacy reference-page hero that had no home in the
 * CMS, so the editorial content pages rendered without them:
 *
 * - `eyebrow` — the mono kicker above the h1 ("// VETERANS BENEFITS",
 *   "// MILITARY MONEY · CREDIT CARDS"). Per page, not derivable from the family.
 * - `disclosure` — the independence-disclosure body. `key_facts` and the trust
 *   columns already exist, but the disclosure copy is page-SPECIFIC (the VA guides
 *   name the VA and an accredited representative; the credit-cards guide carries an
 *   FTC advertiser disclosure), and the shared partial's default wording is wrong
 *   for all of them. Null = fall back to the partial's standard reference wording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('eyebrow')->nullable()->after('h1');
            $table->text('disclosure')->nullable()->after('key_facts');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['eyebrow', 'disclosure']);
        });
    }
};
