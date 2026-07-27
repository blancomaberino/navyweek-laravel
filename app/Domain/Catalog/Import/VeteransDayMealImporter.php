<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\Models\VeteransDayMeal;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the Veterans Day free-meal roundup. A single flat table —
 * an idempotent slug upsert in one transaction. Imports every row regardless of
 * status (including `pending`): the render gate (`isRenderable()` / the repo's
 * `verified()`) filters on read, so pending rows are preserved for the audit
 * trail. `eligibility` (an `AsEnumCollection`) stores its backing strings and
 * validates on read; `redemption`/`status` are scalar enums validated on write.
 *
 * Upsert, not sync: a meal absent from a later artifact is left in place.
 */
final class VeteransDayMealImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            $rows = SeedArtifact::read('veterans-day-meals');

            foreach ($rows as $row) {
                VeteransDayMeal::query()->updateOrCreate(['slug' => $row['slug']], $row);
            }

            return ['veterans_day_meals' => count($rows)];
        });
    }
}
