<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\AirShowStatus;
use App\Domain\Pillars\Enums\FleetWeekSeason;
use App\Domain\Pillars\Enums\FleetWeekStatus;
use App\Domain\Pillars\Import\EventGuidesImporter;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Shared\Import\SeedArtifact;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

/** @return array<int, array<string, mixed>> */
function guideArtifact(string $name): array
{
    return SeedArtifact::read($name);
}

it('imports every fleet-week, air-show and the hub from the committed artifacts', function () {
    $counts = app(EventGuidesImporter::class)->import();

    expect($counts['fleet_weeks'])->toBe(count(guideArtifact('fleet-weeks')))
        ->and($counts['air_shows'])->toBe(count(guideArtifact('air-shows')))
        ->and($counts['air_show_hub'])->toBe(count(guideArtifact('air-show-hub')))
        ->and(FleetWeek::count())->toBe(count(guideArtifact('fleet-weeks')))->toBeGreaterThan(0)
        ->and(AirShow::count())->toBe(count(guideArtifact('air-shows')))->toBeGreaterThan(0)
        ->and(AirShowHubMeta::count())->toBe(1);
});

it('preserves fleet-week enums, block JSON and polymorphic children', function () {
    app(EventGuidesImporter::class)->import();

    $row = collect(guideArtifact('fleet-weeks'))->firstWhere('slug', 'san-francisco');
    $fw = FleetWeek::query()->where('slug', 'san-francisco')->sole();

    expect($fw->season)->toBeInstanceOf(FleetWeekSeason::class)
        ->and($fw->status)->toBeInstanceOf(FleetWeekStatus::class)
        ->and($fw->has_official_fleet_week)->toBeBool()
        ->and($fw->schedule)->toBeArray()->not->toBeEmpty()
        ->and($fw->faqs()->count())->toBe(count($row['faqs']))
        ->and($fw->sources()->count())->toBe(count($row['sources']))
        // The source `publisher` provenance is preserved (not dropped in the lift).
        ->and($fw->sources->first()->publisher)->not->toBeNull();
});

it('imports a Tier-3 fleet week with the festival block nulled out', function () {
    app(EventGuidesImporter::class)->import();

    $philly = FleetWeek::query()->where('slug', 'philadelphia')->sole();

    expect($philly->status)->toBe(FleetWeekStatus::None)
        ->and($philly->status->hasOfficialEvent())->toBeFalse()
        ->and($philly->has_official_fleet_week)->toBeFalse()
        ->and($philly->festival)->toBeNull();
});

it('preserves air-show enums and the three render-gate variants', function () {
    app(EventGuidesImporter::class)->import();

    $oceana = AirShow::query()->where('slug', 'oceana')->sole();
    $sanDiego = AirShow::query()->where('slug', 'san-diego')->sole();
    $andrews = AirShow::query()->where('slug', 'andrews')->sole();

    expect($oceana->admission)->toBeInstanceOf(Admission::class)
        ->and($oceana->status)->toBeInstanceOf(AirShowStatus::class)
        ->and($oceana->sections)->toBeArray()->not->toBeEmpty()
        ->and($oceana->location)->toBeArray()
        // Full guide, confirmed date, no canonical → emits Event JSON-LD.
        ->and($oceana->emitsEventSchema())->toBeTrue()
        // Disambiguation page canonicalizes elsewhere → suppressed.
        ->and($sanDiego->canonical_override)->toBe('/air-show/miramar')
        ->and($sanDiego->emitsEventSchema())->toBeFalse()
        // Unconfirmed date: empty start/end round-trips through the string column → suppressed.
        ->and($andrews->date_unconfirmed)->toBeTrue()
        ->and($andrews->start_date)->toBe('')
        ->and($andrews->emitsEventSchema())->toBeFalse();
});

it('imports the single hub with its FAQs attached', function () {
    app(EventGuidesImporter::class)->import();

    $hubRow = guideArtifact('air-show-hub')[0];
    $hub = AirShowHubMeta::query()->sole();

    expect($hub->base_path)->toBe($hubRow['base_path'])
        ->and($hub->key_facts)->toBeArray()
        ->and($hub->faqs()->count())->toBe(count($hubRow['faqs']));
});

it('is idempotent — re-running upserts without duplicating rows or children', function () {
    $importer = app(EventGuidesImporter::class);
    $importer->import();

    $faqs = Faq::count();
    $sources = Source::count();

    $importer->import();

    expect(FleetWeek::count())->toBe(count(guideArtifact('fleet-weeks')))
        ->and(AirShow::count())->toBe(count(guideArtifact('air-shows')))
        ->and(AirShowHubMeta::count())->toBe(1)
        ->and(Faq::count())->toBe($faqs)
        ->and(Source::count())->toBe($sources);
});

it('runs end-to-end via the import:event-guides artisan command', function () {
    $this->artisan('import:event-guides')->assertSuccessful();

    expect(FleetWeek::count())->toBe(count(guideArtifact('fleet-weeks')));
});
