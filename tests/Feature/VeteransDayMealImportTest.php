<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\MealEligibility;
use App\Domain\Catalog\Enums\MealRedemption;
use App\Domain\Catalog\Enums\MealStatus;
use App\Domain\Catalog\Import\VeteransDayMealImporter;
use App\Domain\Catalog\Models\VeteransDayMeal;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Collection;

/** @return array<int, array<string, mixed>> */
function mealArtifact(): array
{
    return SeedArtifact::read('veterans-day-meals');
}

it('imports every meal from the committed artifact — all statuses', function () {
    $counts = app(VeteransDayMealImporter::class)->import();

    expect($counts['veterans_day_meals'])->toBe(count(mealArtifact()))
        ->and(VeteransDayMeal::count())->toBe(count(mealArtifact()))->toBeGreaterThan(0)
        // Pending rows are imported (not pre-filtered) — the gate is applied on read.
        ->and(VeteransDayMeal::query()->where('status', MealStatus::Pending)->count())->toBeGreaterThan(0);
});

it('casts the eligibility enum collection plus the scalar redemption/status enums', function () {
    app(VeteransDayMealImporter::class)->import();

    $row = collect(mealArtifact())->firstWhere('slug', 'texas-roadhouse');
    $meal = VeteransDayMeal::query()->where('slug', 'texas-roadhouse')->sole();

    expect($meal->eligibility)->toBeInstanceOf(Collection::class)
        ->and($meal->eligibility)->toHaveCount(count($row['eligibility']))
        ->and($meal->eligibility->first())->toBeInstanceOf(MealEligibility::class)
        ->and($meal->redemption)->toBeInstanceOf(MealRedemption::class)
        ->and($meal->status)->toBe(MealStatus::Verified)
        ->and($meal->dependents_eligible)->toBeBool()
        ->and($meal->nationwide)->toBeTrue()
        // discount_slug is a soft FK to a Connection — set on this row.
        ->and($meal->discount_slug)->toBe('texas-roadhouse-military-discount')
        // offer_date is a free-text string column, last_verified_at is a real date.
        ->and($meal->offer_date)->toBeString()
        ->and($meal->last_verified_at->toDateString())->toBe('2026-06-29');
});

it('applies the render gate: verified+sourced renders, pending is withheld', function () {
    app(VeteransDayMealImporter::class)->import();

    $verified = VeteransDayMeal::query()->where('status', MealStatus::Verified)->first();
    $pending = VeteransDayMeal::query()->where('status', MealStatus::Pending)->first();

    expect($verified->isRenderable())->toBeTrue()
        ->and($pending)->not->toBeNull()
        ->and($pending->isRenderable())->toBeFalse();
});

it('leaves discount_slug null on meals that do not name a brand guide', function () {
    app(VeteransDayMealImporter::class)->import();

    // Only texas-roadhouse sets discountSlug in the source; the rest are null.
    expect(VeteransDayMeal::query()->whereNull('discount_slug')->count())
        ->toBe(count(mealArtifact()) - 1);
});

it('is idempotent — re-running upserts without duplicating rows', function () {
    $importer = app(VeteransDayMealImporter::class);
    $importer->import();
    $importer->import();

    expect(VeteransDayMeal::count())->toBe(count(mealArtifact()));
});

it('runs end-to-end via the import:veterans-day-meals artisan command', function () {
    $this->artisan('import:veterans-day-meals')->assertSuccessful();

    expect(VeteransDayMeal::count())->toBe(count(mealArtifact()));
});
