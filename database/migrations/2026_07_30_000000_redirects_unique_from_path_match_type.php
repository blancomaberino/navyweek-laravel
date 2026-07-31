<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A `from_path` can legitimately carry BOTH an exact rule (redirecting that path
 * itself) and a prefix rule (redirecting its descendants) — e.g. `/old/` is a live
 * exact page while `/old/**` still redirects elsewhere. The original single-column
 * unique on `from_path` made that impossible and let a page rename overwrite an
 * admin-managed prefix rule. Widen the constraint to `(from_path, match_type)` so
 * the two coexist; `matchFor` already filters by `match_type`, so lookups are
 * unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropUnique(['from_path']);
            $table->unique(['from_path', 'match_type']);
        });
    }

    public function down(): void
    {
        // up() lets a `from_path` carry both an exact and a prefix rule; restoring the
        // single-column unique would fail with a duplicate-key error on any such path.
        // Preflight and abort with an actionable message rather than crash mid-rollback
        // (reconciling which rule to keep is a human decision, not a lossy auto-drop).
        $conflicts = DB::table('redirects')
            ->select('from_path')
            ->groupBy('from_path')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('from_path');

        if ($conflicts->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot restore the single-column unique on redirects.from_path: these paths carry more than one rule '
                .'(e.g. an exact + a prefix) and must be reconciled first: '.$conflicts->implode(', ')
            );
        }

        Schema::table('redirects', function (Blueprint $table) {
            $table->dropUnique(['from_path', 'match_type']);
            $table->unique(['from_path']);
        });
    }
};
