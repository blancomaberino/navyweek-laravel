<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Import;

use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the event-guide aggregates: Fleet Week city guides, air-show
 * event guides, and the single air-show hub. Each is an idempotent upsert (by
 * `slug`, or `base_path` for the single hub) inside one transaction, with the
 * polymorphic FAQs/sources replaced. The hub carries FAQs only. Enum columns
 * validate on write; rows absent from a later artifact are left in place.
 */
final class EventGuidesImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(fn (): array => [
            'fleet_weeks' => $this->importFleetWeeks(),
            'air_shows' => $this->importAirShows(),
            'air_show_hub' => $this->importHub(),
        ]);
    }

    private function importFleetWeeks(): int
    {
        $rows = SeedArtifact::read('fleet-weeks');

        foreach ($rows as $row) {
            /** @var list<array<string, mixed>> $faqs */
            $faqs = $row['faqs'] ?? [];
            /** @var list<array<string, mixed>> $sources */
            $sources = $row['sources'] ?? [];
            unset($row['faqs'], $row['sources']);

            $model = FleetWeek::query()->updateOrCreate(['slug' => $row['slug']], $row);

            $model->faqs()->delete();
            $model->faqs()->createMany($faqs);

            $model->sources()->delete();
            $model->sources()->createMany($sources);
        }

        return count($rows);
    }

    private function importAirShows(): int
    {
        $rows = SeedArtifact::read('air-shows');

        foreach ($rows as $row) {
            /** @var list<array<string, mixed>> $faqs */
            $faqs = $row['faqs'] ?? [];
            /** @var list<array<string, mixed>> $sources */
            $sources = $row['sources'] ?? [];
            unset($row['faqs'], $row['sources']);

            $model = AirShow::query()->updateOrCreate(['slug' => $row['slug']], $row);

            $model->faqs()->delete();
            $model->faqs()->createMany($faqs);

            $model->sources()->delete();
            $model->sources()->createMany($sources);
        }

        return count($rows);
    }

    private function importHub(): int
    {
        $rows = SeedArtifact::read('air-show-hub');

        foreach ($rows as $row) {
            /** @var list<array<string, mixed>> $faqs */
            $faqs = $row['faqs'] ?? [];
            unset($row['faqs']);

            $hub = AirShowHubMeta::query()->updateOrCreate(['base_path' => $row['base_path']], $row);

            $hub->faqs()->delete();
            $hub->faqs()->createMany($faqs);
        }

        return count($rows);
    }
}
