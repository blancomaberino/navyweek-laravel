<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Support\DiscountCategoryOrdering;

it('puts pinned page slugs first and keeps the rest in the given order', function () {
    $category = DiscountCategory::factory()->create([
        'pinned' => ['hertz-military-discount', 'avis-military-discount'],
        'excluded' => ['secret-brand-military-discount'],
    ]);

    // Keys are PAGE slugs, arriving brand A–Z from the repository.
    $bySlug = [
        'alamo-military-discount' => 'Alamo',
        'avis-military-discount' => 'Avis',
        'hertz-military-discount' => 'Hertz',
        'secret-brand-military-discount' => 'Secret',
        'zipcar-military-discount' => 'Zipcar',
    ];

    expect(array_values(DiscountCategoryOrdering::apply($category, $bySlug)))
        ->toBe(['Hertz', 'Avis', 'Alamo', 'Zipcar']);
});

it('honours an explicit order over pinned, sending unnamed entries to the end', function () {
    $category = DiscountCategory::factory()->create([
        'order' => ['delta-military-discount', 'united-military-discount'],
        'pinned' => ['jetblue-military-discount'],
    ]);

    $bySlug = [
        'alaska-military-discount' => 'Alaska',
        'delta-military-discount' => 'Delta',
        'jetblue-military-discount' => 'JetBlue',
        'united-military-discount' => 'United',
    ];

    expect(array_values(DiscountCategoryOrdering::apply($category, $bySlug)))
        ->toBe(['Delta', 'United', 'Alaska', 'JetBlue']);
});

it('excludes by page slug', function () {
    $category = DiscountCategory::factory()->create([
        'excluded' => ['airbnb-military-discount'],
    ]);

    $bySlug = ['airbnb-military-discount' => 'Airbnb', 'marriott-military-discount' => 'Marriott'];

    expect(DiscountCategoryOrdering::apply($category, $bySlug))
        ->toBe(['marriott-military-discount' => 'Marriott']);
});

it('passes everything through untouched when nothing is curated', function () {
    $category = DiscountCategory::factory()->create(['pinned' => null, 'order' => null, 'excluded' => null]);

    $bySlug = ['a-military-discount' => 'A', 'b-military-discount' => 'B'];

    expect(DiscountCategoryOrdering::apply($category, $bySlug))->toBe($bySlug);
});
