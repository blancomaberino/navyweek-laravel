<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the discount category hubs (`/discount/<slug>`). A single
 * flat table — no polymorphic children — so it is just an idempotent slug upsert
 * inside one transaction. The intro/pinned/excluded/order JSON columns round-trip
 * through the array casts; `last_verified` is a human label kept as a string.
 *
 * Upsert, not sync: a hub absent from a later artifact is left in place.
 */
final class DiscountCategoryImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            $rows = SeedArtifact::read('discount-categories');

            foreach ($rows as $row) {
                DiscountCategory::query()->updateOrCreate(['slug' => $row['slug']], $row);
            }

            return ['discount_categories' => count($rows)];
        });
    }
}
