<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the local (geographic) discount guides — a nested
 * aggregate: `local_discounts` → `local_stores` → `local_store_hours`, plus the
 * parent's polymorphic FAQs/sources. One transaction, idempotent.
 *
 * The parent upserts on its composite natural key (`state` + `city` +
 * `business_slug`) and replaces its FAQs/sources. Stores and hours have no
 * natural unique key, so each parent's store set is replaced wholesale (delete +
 * re-insert in array order); the `local_store_hours` rows clear via the schema's
 * cascade-on-delete. FKs and `sort_order` are synthesized from the nesting +
 * array position (the legacy data carries no flat FK fields).
 *
 * Upsert, not sync: a discount absent from a later artifact is left in place.
 */
final class LocalDiscountImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            $rows = SeedArtifact::read('local-discounts');
            $storeCount = 0;
            $hoursCount = 0;

            foreach ($rows as $row) {
                /** @var list<array<string, mixed>> $faqs */
                $faqs = $row['faqs'] ?? [];
                /** @var list<array<string, mixed>> $sources */
                $sources = $row['sources'] ?? [];
                /** @var list<array<string, mixed>> $stores */
                $stores = $row['stores'] ?? [];
                unset($row['faqs'], $row['sources'], $row['stores']);

                $discount = LocalDiscount::query()->updateOrCreate(
                    ['state' => $row['state'], 'city' => $row['city'], 'business_slug' => $row['business_slug']],
                    $row,
                );

                $discount->replaceFaqs($faqs);
                $discount->replaceSources($sources);

                // Stores/hours have no natural key → replace the parent's set
                // wholesale; the hours rows clear via cascade-on-delete.
                $discount->stores()->delete();
                foreach ($stores as $storeRow) {
                    /** @var list<array<string, mixed>> $hours */
                    $hours = $storeRow['hours'] ?? [];
                    unset($storeRow['hours']);

                    $store = $discount->stores()->create($storeRow);
                    $store->hours()->createMany($hours);

                    $storeCount++;
                    $hoursCount += count($hours);
                }
            }

            return [
                'local_discounts' => count($rows),
                'local_stores' => $storeCount,
                'local_store_hours' => $hoursCount,
            ];
        });
    }
}
