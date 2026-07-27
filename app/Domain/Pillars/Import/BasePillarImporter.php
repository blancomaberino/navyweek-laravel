<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Import;

use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Models\OverseasCountry;
use App\Domain\Pillars\Models\UsState;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the bases pillar — the proving ground for the data-migration
 * pipeline. Loads the `us-states`, `overseas-countries`, and `bases` artifacts and
 * upserts them into their tables, then replaces each base's polymorphic FAQs and
 * sources. Idempotent: keyed on `slug`, so re-running updates in place and never
 * duplicates. The whole run is one transaction — a bad row rolls the batch back.
 *
 * This is an upsert, not a sync: rows absent from a later artifact are left in place,
 * never pruned. Fine for a one-way Stage-B import (the exporter's set only grows); a
 * prune pass would be a separate concern if artifacts ever shrink.
 */
final class BasePillarImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            $states = $this->importStates();
            $countries = $this->importCountries();
            $bases = $this->importBases();

            return [
                'us_states' => $states,
                'overseas_countries' => $countries,
                'bases' => $bases,
            ];
        });
    }

    private function importStates(): int
    {
        $rows = SeedArtifact::read('us-states');

        foreach ($rows as $row) {
            UsState::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }

        return count($rows);
    }

    private function importCountries(): int
    {
        $rows = SeedArtifact::read('overseas-countries');

        foreach ($rows as $row) {
            OverseasCountry::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }

        return count($rows);
    }

    private function importBases(): int
    {
        $rows = SeedArtifact::read('bases');

        foreach ($rows as $row) {
            // The polymorphic children ride in the artifact; lift them out before
            // the base upsert, then replace them so a re-import stays idempotent.
            /** @var list<array<string, mixed>> $faqs */
            $faqs = $row['faqs'] ?? [];
            /** @var list<array<string, mixed>> $sources */
            $sources = $row['sources'] ?? [];
            unset($row['faqs'], $row['sources']);

            $base = Base::query()->updateOrCreate(['slug' => $row['slug']], $row);

            $base->faqs()->delete();
            $base->faqs()->createMany($faqs);

            $base->sources()->delete();
            $base->sources()->createMany($sources);
        }

        return count($rows);
    }
}
