<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Import;

use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the Navy Week stops — the legacy `events` + `CityData` +
 * `CityExtras` folded into one row per city by the Stage-A exporter. Idempotent:
 * keyed on `slug`, one transaction, polymorphic FAQs/sources replaced (not
 * appended) on each run. The `status` enum column validates on write; the
 * lat/lng decimals and the display-list JSON columns (navy_assets, venues,
 * daily_schedule, …) round-trip through the casts.
 *
 * Upsert, not sync: rows absent from a later artifact are left in place.
 */
final class NavyWeekEventsImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            $rows = SeedArtifact::read('navy-week-events');

            foreach ($rows as $row) {
                /** @var list<array<string, mixed>> $faqs */
                $faqs = $row['faqs'] ?? [];
                /** @var list<array<string, mixed>> $sources */
                $sources = $row['sources'] ?? [];
                unset($row['faqs'], $row['sources']);

                $event = NavyWeekEvent::query()->updateOrCreate(['slug' => $row['slug']], $row);

                $event->replaceFaqs($faqs);
                $event->replaceSources($sources);
            }

            return ['navy_week_events' => count($rows)];
        });
    }
}
