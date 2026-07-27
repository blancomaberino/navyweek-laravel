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

it('orders matched connections: pinned first, then the rest A–Z, excluding hidden', function () {
    $category = DiscountCategory::factory()->create([
        'match_category' => 'Car Rental',
        'pinned' => ['hertz', 'avis'],
        'excluded' => ['secret-brand'],
    ]);

    Connection::factory()->create(['slug' => 'avis', 'brand' => 'Avis', 'category' => 'Car Rental']);
    Connection::factory()->create(['slug' => 'hertz', 'brand' => 'Hertz', 'category' => 'Car Rental']);
    Connection::factory()->create(['slug' => 'zipcar', 'brand' => 'Zipcar', 'category' => 'Car Rental']);
    Connection::factory()->create(['slug' => 'alamo', 'brand' => 'Alamo', 'category' => 'Car Rental']);
    Connection::factory()->create(['slug' => 'secret-brand', 'brand' => 'Secret', 'category' => 'Car Rental']);
    // A different category must not leak in.
    Connection::factory()->create(['slug' => 'marriott', 'brand' => 'Marriott', 'category' => 'Hotels & Travel']);

    $result = $this->repository->orderedConnections($category);

    // hertz, avis (pinned order) then alamo, zipcar (A–Z); secret-brand excluded.
    expect($result->pluck('slug')->all())->toBe(['hertz', 'avis', 'alamo', 'zipcar']);
});

it('honours an explicit order, sending unnamed brands to the end A–Z', function () {
    $category = DiscountCategory::factory()->create([
        'match_category' => 'Flights',
        'order' => ['delta', 'united'],
    ]);

    Connection::factory()->create(['slug' => 'united', 'brand' => 'United', 'category' => 'Flights']);
    Connection::factory()->create(['slug' => 'delta', 'brand' => 'Delta', 'category' => 'Flights']);
    Connection::factory()->create(['slug' => 'jetblue', 'brand' => 'JetBlue', 'category' => 'Flights']);
    Connection::factory()->create(['slug' => 'alaska', 'brand' => 'Alaska', 'category' => 'Flights']);

    $result = $this->repository->orderedConnections($category);

    // delta, united (explicit) then alaska, jetblue (unnamed → end, A–Z).
    expect($result->pluck('slug')->all())->toBe(['delta', 'united', 'alaska', 'jetblue']);
});
