<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\DiscountCategory;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\EloquentDiscountCategoryRepository;
use App\Domain\Crm\Models\Connection;

beforeEach(function () {
    $this->repository = app(DiscountCategoryRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentDiscountCategoryRepository::class);
});

it('finds a category by slug', function () {
    $category = DiscountCategory::factory()->create(['slug' => 'flights-military-veteran']);

    expect($this->repository->findBySlug('flights-military-veteran')?->is($category))->toBeTrue()
        ->and($this->repository->findBySlug('missing'))->toBeNull();
});

it('returns all hubs in id (registry insertion) order', function () {
    DiscountCategory::factory()->create(['slug' => 'first-military-veteran']);
    DiscountCategory::factory()->create(['slug' => 'second-military-veteran']);
    DiscountCategory::factory()->create(['slug' => 'third-military-veteran']);

    expect($this->repository->all()->pluck('slug')->all())
        ->toBe(['first-military-veteran', 'second-military-veteran', 'third-military-veteran']);
});

it('returns the category\'s connections brand A-Z, case-insensitively', function () {
    $category = DiscountCategory::factory()->create(['match_category' => 'Car Rental']);

    Connection::factory()->create(['slug' => 'zipcar', 'brand' => 'Zipcar', 'category' => 'Car Rental']);
    Connection::factory()->create(['slug' => 'alamo', 'brand' => 'Alamo', 'category' => 'Car Rental']);
    Connection::factory()->create(['slug' => 'ebay-cars', 'brand' => 'eBay Motors', 'category' => 'Car Rental']);
    // A different category must not leak in.
    Connection::factory()->create(['slug' => 'marriott', 'brand' => 'Marriott', 'category' => 'Hotels & Travel']);

    $result = $this->repository->orderedConnections($category);

    // Case-insensitive: "eBay Motors" sorts between Alamo and Zipcar, not after
    // both (a byte-order sort would push every lowercase-styled brand to the end).
    expect($result->pluck('slug')->all())->toBe(['alamo', 'ebay-cars', 'zipcar']);
});

it('leaves the curated lists alone — they are page slugs, applied downstream', function () {
    // Real data pins PAGE slugs (…-military-discount). This method only sees
    // connection slugs, so it must not try to match them: doing so silently did
    // nothing, which is how the curated order went missing on the live hubs.
    $category = DiscountCategory::factory()->create([
        'match_category' => 'Hotels & Travel',
        'pinned' => ['marriott-military-discount'],
        'excluded' => ['airbnb-military-discount'],
    ]);

    Connection::factory()->create(['slug' => 'airbnb', 'brand' => 'Airbnb', 'category' => 'Hotels & Travel']);
    Connection::factory()->create(['slug' => 'marriott', 'brand' => 'Marriott', 'category' => 'Hotels & Travel']);

    expect($this->repository->orderedConnections($category)->pluck('slug')->all())
        ->toBe(['airbnb', 'marriott']);
});
