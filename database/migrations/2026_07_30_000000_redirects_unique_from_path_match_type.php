<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropUnique(['from_path', 'match_type']);
            $table->unique(['from_path']);
        });
    }
};
