<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Import;

use App\Domain\Pillars\Models\Rank;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the ranks pillar — the second reference pillar through the
 * data-migration framework. Loads the `ranks` artifact and upserts the
 * single-table-inheritance rows (all 6 categories in one table), then replaces
 * each rank's polymorphic FAQs and sources. Idempotent: keyed on `slug`, one
 * transaction. The three scalar enum columns (`category`,
 * `designator_community`, `rating_community`) validate on write — an unknown
 * value throws and rolls the batch back; `era_tags` (an `AsEnumCollection`) stores
 * its backing-value strings verbatim and validates on read.
 *
 * Upsert, not sync: rows absent from a later artifact are left in place.
 */
final class RankPillarImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            $rows = SeedArtifact::read('ranks');

            foreach ($rows as $row) {
                /** @var list<array<string, mixed>> $faqs */
                $faqs = $row['faqs'] ?? [];
                /** @var list<array<string, mixed>> $sources */
                $sources = $row['sources'] ?? [];
                unset($row['faqs'], $row['sources']);

                $rank = Rank::query()->updateOrCreate(['slug' => $row['slug']], $row);

                $rank->replaceFaqs($faqs);
                $rank->replaceSources($sources);
            }

            return ['ranks' => count($rows)];
        });
    }
}
