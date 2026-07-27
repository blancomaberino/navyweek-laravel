<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\BaseType;
use App\Domain\Pillars\Enums\RegionType;
use App\Domain\Pillars\Import\BasePillarImporter;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Models\OverseasCountry;
use App\Domain\Pillars\Models\UsState;
use App\Domain\Shared\Import\SeedArtifact;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

/** @return array<int, array<string, mixed>> */
function artifact(string $name): array
{
    return SeedArtifact::read($name);
}

it('imports every row from the committed seed artifacts', function () {
    $counts = app(BasePillarImporter::class)->import();

    // Compare to the artifact lengths rather than magic numbers — the invariant
    // is "the importer loads exactly what the exporter emitted".
    expect($counts['us_states'])->toBe(count(artifact('us-states')))
        ->and($counts['overseas_countries'])->toBe(count(artifact('overseas-countries')))
        ->and($counts['bases'])->toBe(count(artifact('bases')))
        ->and(UsState::count())->toBe(count(artifact('us-states')))
        ->and(OverseasCountry::count())->toBe(count(artifact('overseas-countries')))
        ->and(Base::count())->toBe(count(artifact('bases')))
        // Floor: an empty/truncated artifact would make count == count pass
        // vacuously, so assert the pipeline actually moved real data.
        ->and(Base::count())->toBeGreaterThan(0)
        ->and(UsState::count())->toBeGreaterThan(0);
});

it('preserves enums, JSON, the soft-FK and polymorphic children on a real base', function () {
    app(BasePillarImporter::class)->import();

    // naval-station-norfolk is a CONUS (state-based) base with FAQs + sources.
    $norfolk = collect(artifact('bases'))->firstWhere('slug', 'naval-station-norfolk');
    $base = Base::query()->where('slug', 'naval-station-norfolk')->sole();

    expect($base->type)->toBeInstanceOf(BaseType::class)
        ->and($base->region_type)->toBe(RegionType::State)
        ->and($base->key_facts)->toBeArray()->not->toBeEmpty()
        ->and($base->faqs()->count())->toBe(count($norfolk['faqs']))
        ->and($base->sources()->count())->toBe(count($norfolk['sources']))
        ->and($base->faqs->first()->question)->toBe($norfolk['faqs'][0]['question'])
        // The `state` slug soft-FK resolves to the seeded us_states row.
        ->and($base->usState)->not->toBeNull()
        ->and($base->usState->slug)->toBe($base->state);
});

it('imports overseas bases with their country soft-FK resolving', function () {
    app(BasePillarImporter::class)->import();

    $overseas = Base::query()->where('region_type', RegionType::Country->value)->first();

    expect($overseas)->not->toBeNull()
        ->and($overseas->isOverseas())->toBeTrue()
        ->and($overseas->overseasCountry)->not->toBeNull();
});

it('is idempotent — re-running upserts without duplicating rows or children', function () {
    $importer = app(BasePillarImporter::class);
    $importer->import();

    $bases = Base::count();
    $faqs = Faq::count();
    $sources = Source::count();

    $importer->import();

    expect(Base::count())->toBe($bases)
        ->and(UsState::count())->toBe(count(artifact('us-states')))
        ->and(Faq::count())->toBe($faqs)
        ->and(Source::count())->toBe($sources);
});

it('runs end-to-end via the import:bases artisan command', function () {
    $this->artisan('import:bases')->assertSuccessful();

    expect(Base::count())->toBe(count(artifact('bases')));
});
