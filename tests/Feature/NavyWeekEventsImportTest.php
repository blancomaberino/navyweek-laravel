<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Pillars\Import\NavyWeekEventsImporter;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Shared\Import\SeedArtifact;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

/** @return array<int, array<string, mixed>> */
function navyWeekArtifact(): array
{
    return SeedArtifact::read('navy-week-events');
}

it('imports every Navy Week stop from the committed artifact', function () {
    $counts = app(NavyWeekEventsImporter::class)->import();

    expect($counts['navy_week_events'])->toBe(count(navyWeekArtifact()))
        ->and(NavyWeekEvent::count())->toBe(count(navyWeekArtifact()))->toBeGreaterThan(0);
});

it('folds events + CityData + CityExtras into one row with the right casts', function () {
    app(NavyWeekEventsImporter::class)->import();

    $row = collect(navyWeekArtifact())->firstWhere('slug', 'rio-grande-valley');
    $event = NavyWeekEvent::query()->where('slug', 'rio-grande-valley')->sole();

    expect($event->sequence)->toBe(1)
        ->and($event->status)->toBeInstanceOf(NavyWeekStatus::class)
        ->and($event->status)->toBe(NavyWeekStatus::Completed)
        // decimal-as-string lat/lng round-trip
        ->and($event->lat)->toBe('26.2034000')
        // CityData display lists land as JSON arrays
        ->and($event->navy_assets)->toBeArray()->not->toBeEmpty()
        ->and($event->key_venues)->toBeArray()
        ->and($event->military_context)->toBeArray()
        ->and($event->highlights)->toBeArray()
        // CityExtras venues (array of objects) + scalar detail
        ->and($event->venues)->toBeArray()->not->toBeEmpty()
        ->and($event->parking_notes)->toBeString()->not->toBeEmpty()
        ->and($event->cost_summary)->toBeString()->not->toBeEmpty()
        ->and($event->navco_url)->toBe($row['navco_url'])
        ->and($event->anchor_event_url)->toBe($row['anchor_event_url'])
        ->and($event->last_verified_at->toDateString())->toBe('2026-07-13')
        // FAQs + sources lifted to the shared polymorphic tables, in order
        ->and($event->faqs()->count())->toBe(count($row['faqs']))
        ->and($event->sources()->count())->toBe(count($row['sources']));
});

it('carries the sole first-time-location stop and its custom badge', function () {
    app(NavyWeekEventsImporter::class)->import();

    $hilo = NavyWeekEvent::query()->where('slug', 'honolulu-hilo')->sole();

    expect($hilo->first_time_location)->toBeTrue()
        ->and($hilo->first_time_badge)->toBe('First Navy Week visit to Hilo')
        ->and($hilo->isFirstTimeLocation())->toBeTrue();
});

it('stores the daily schedule only for the two cities that have one', function () {
    app(NavyWeekEventsImporter::class)->import();

    $charlotte = NavyWeekEvent::query()->where('slug', 'charlotte')->sole();
    $rgv = NavyWeekEvent::query()->where('slug', 'rio-grande-valley')->sole();

    expect($charlotte->daily_schedule)->toBeArray()->toHaveCount(8)
        // The other cities store null — the runtime TBA-day synthesis is not persisted.
        ->and($rgv->daily_schedule)->toBeNull();
});

it('is idempotent — re-running upserts without duplicating rows or children', function () {
    $importer = app(NavyWeekEventsImporter::class);
    $importer->import();

    $faqs = Faq::count();
    $sources = Source::count();

    $importer->import();

    expect(NavyWeekEvent::count())->toBe(count(navyWeekArtifact()))
        ->and(Faq::count())->toBe($faqs)
        ->and(Source::count())->toBe($sources);
});

it('runs end-to-end via the import:navy-week-events artisan command', function () {
    $this->artisan('import:navy-week-events')->assertSuccessful();

    expect(NavyWeekEvent::count())->toBe(count(navyWeekArtifact()));
});
