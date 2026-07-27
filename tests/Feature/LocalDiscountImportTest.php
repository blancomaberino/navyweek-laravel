<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\LocalVerification;
use App\Domain\Catalog\Import\LocalDiscountImporter;
use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Models\LocalStore;
use App\Domain\Catalog\Models\LocalStoreHours;
use App\Domain\Shared\Import\SeedArtifact;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

/** @return array<int, array<string, mixed>> */
function localArtifact(): array
{
    return SeedArtifact::read('local-discounts');
}

/** Total nested children of a given key across the artifact. */
function localChildTotal(string $key): int
{
    $total = 0;
    foreach (localArtifact() as $discount) {
        foreach ($discount['stores'] as $store) {
            $total += $key === 'stores' ? 1 : count($store['hours']);
        }
    }

    return $total;
}

it('imports every discount, store and hours row from the committed artifact', function () {
    $counts = app(LocalDiscountImporter::class)->import();

    expect($counts['local_discounts'])->toBe(count(localArtifact()))
        ->and($counts['local_stores'])->toBe(localChildTotal('stores'))
        ->and($counts['local_store_hours'])->toBe(localChildTotal('hours'))
        ->and(LocalDiscount::count())->toBe(count(localArtifact()))->toBeGreaterThan(0)
        ->and(LocalStore::count())->toBe(localChildTotal('stores'))
        ->and(LocalStoreHours::count())->toBe(localChildTotal('hours'));
});

it('maps the parent: enum, the 5 audience flags, JSON lists and the soft state FK', function () {
    app(LocalDiscountImporter::class)->import();

    $row = collect(localArtifact())->firstWhere('business_slug', 'houston-zoo-military-veteran');
    $zoo = LocalDiscount::query()->where('business_slug', 'houston-zoo-military-veteran')->sole();

    expect($zoo->verification)->toBe(LocalVerification::ReservationId)
        ->and($zoo->state)->toBe('texas')
        // 5 military-audience flags off the nested `audience` object
        ->and($zoo->active_duty)->toBeTrue()
        ->and($zoo->military_family)->toBeBool()
        ->and($zoo->eligibility)->toBeArray()->toHaveCount(count($row['eligibility']))
        ->and($zoo->tiers)->toBeArray()->not->toBeEmpty()
        ->and($zoo->redeem_in_store)->toBeArray()
        ->and($zoo->nearby_bases)->toBeArray()
        ->and($zoo->key_facts)->toBeArray()
        // last_verified is a human label, kept verbatim
        ->and($zoo->last_verified)->toBe($row['last_verified'])->toBeString()
        ->and($zoo->faqs()->count())->toBe(count($row['faqs']))
        ->and($zoo->sources()->count())->toBe(count($row['sources']))
        // source publisher provenance is preserved
        ->and($zoo->sources->first()->publisher)->not->toBeNull();
});

it('nests stores under the discount and hours under the store, in order', function () {
    app(LocalDiscountImporter::class)->import();

    // HMNS has one store with two hours spans (Mon–Thu / Fri–Sun).
    $hmns = LocalDiscount::query()->where('business_slug', 'hmns-military-veteran')->sole();

    expect($hmns->stores()->count())->toBe(1);

    $store = $hmns->stores()->sole();
    expect($store->lat)->toBeString()
        ->and($store->distance_label)->toBeNull()
        ->and($store->hours()->count())->toBe(2);

    $firstSpan = $store->hours()->first();
    expect($firstSpan->day_of_week)->toBeArray()->not->toBeEmpty()
        ->and($firstSpan->opens)->not->toBeNull()
        ->and($firstSpan->sort_order)->toBe(0);
});

it('is idempotent — re-running replaces children without duplicates or orphans', function () {
    $importer = app(LocalDiscountImporter::class);
    $importer->import();

    $faqs = Faq::count();
    $sources = Source::count();

    $importer->import();

    expect(LocalDiscount::count())->toBe(count(localArtifact()))
        ->and(LocalStore::count())->toBe(localChildTotal('stores'))
        // hours must not orphan or duplicate on re-import (cascade + wholesale replace)
        ->and(LocalStoreHours::count())->toBe(localChildTotal('hours'))
        ->and(Faq::count())->toBe($faqs)
        ->and(Source::count())->toBe($sources);
});

it('runs end-to-end via the import:local-discounts artisan command', function () {
    $this->artisan('import:local-discounts')->assertSuccessful();

    expect(LocalStoreHours::count())->toBe(localChildTotal('hours'));
});
