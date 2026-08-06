<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The editorial-policy box is page-specific in ALL SIX bullets, not two.
 *
 * The reference pages share the generic wording that used to be hardcoded in the
 * Blade partial, but the YMYL guides each write their own — `VaDisability.tsx`
 * has its own `EditorialPolicyBox()` whose Independence bullet names the VA and
 * disclaims paid attorney/VSO placement, and whose Reviewer bullet carries the
 * load-bearing "not a VA-accredited representative" disclaimer. Leaving those as
 * fixed house copy both lost ~90-115px per guide and, more importantly, published
 * a weaker disclaimer than the live site on money-and-benefits pages.
 *
 * All six are nullable: the partial keeps the generic wording as its fallback, so
 * only pages that actually differ carry a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->text('editorial_independence')->nullable()->after('editorial_review_cadence');
            $table->text('editorial_reviewer_note')->nullable()->after('editorial_independence');
            $table->text('editorial_corrections')->nullable()->after('editorial_reviewer_note');
            $table->text('editorial_not_advice')->nullable()->after('editorial_corrections');
            // The corrections box asks for a different kind of source per family
            // (".gov or .mil" on the reference pages, "VA.gov or eCFR" on the VA guide).
            $table->text('corrections_note')->nullable()->after('editorial_not_advice');
            // The reference pages close the byline with "How we research & review";
            // the YMYL guides end it at the dates line.
            $table->boolean('shows_process_link')->default(true)->after('corrections_note');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn([
                'editorial_independence',
                'editorial_reviewer_note',
                'editorial_corrections',
                'editorial_not_advice',
                'corrections_note',
                'shows_process_link',
            ]);
        });
    }
};
