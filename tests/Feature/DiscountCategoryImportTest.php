<?php

declare(strict_types=1);

use App\Domain\Catalog\Import\DiscountCategoryImporter;
use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Shared\Import\SeedArtifact;

/** @return array<int, array<string, mixed>> */
function categoryArtifact(): array
{
    return SeedArtifact::read('discount-categories');
}

it('imports every category hub from the committed artifact', function () {
    $counts = app(DiscountCategoryImporter::class)->import();

    expect($counts['discount_categories'])->toBe(count(categoryArtifact()))
        ->and(DiscountCategory::count())->toBe(count(categoryArtifact()))->toBeGreaterThan(0);
});

it('maps the columns and JSON arrays, keeping last_verified a human string', function () {
    app(DiscountCategoryImporter::class)->import();

    $row = collect(categoryArtifact())->firstWhere('slug', 'flights-military-veteran');
    $cat = DiscountCategory::query()->where('slug', 'flights-military-veteran')->sole();

    expect($cat->match_category)->toBe($row['match_category'])
        ->and($cat->intro)->toBeArray()->not->toBeEmpty()
        // flights is the one hub that sets both pinned and excluded overrides.
        ->and($cat->pinned)->toBeArray()->not->toBeEmpty()
        ->and($cat->excluded)->toBeArray()->not->toBeEmpty()
        ->and($cat->order)->toBeNull()
        ->and($cat->date_published)->not->toBeNull()
        // last_verified is a human label, not a date — kept verbatim.
        ->and($cat->last_verified)->toBe($row['last_verified'])
        ->and($cat->last_verified)->toBeString();
});

it('leaves the ordering overrides null when a hub omits them', function () {
    app(DiscountCategoryImporter::class)->import();

    $moving = DiscountCategory::query()->where('slug', 'moving-companies-military-veteran')->sole();

    expect($moving->pinned)->toBeNull()
        ->and($moving->excluded)->toBeNull()
        ->and($moving->order)->toBeNull();
});

it('is idempotent — re-running upserts without duplicating rows', function () {
    $importer = app(DiscountCategoryImporter::class);
    $importer->import();
    $importer->import();

    expect(DiscountCategory::count())->toBe(count(categoryArtifact()));
});

it('runs end-to-end via the import:discount-categories artisan command', function () {
    $this->artisan('import:discount-categories')->assertSuccessful();

    expect(DiscountCategory::count())->toBe(count(categoryArtifact()));
});
