<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Repositories\EloquentLocalDiscountRepository;
use App\Domain\Catalog\Repositories\LocalDiscountRepositoryInterface;

beforeEach(function () {
    $this->repository = app(LocalDiscountRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentLocalDiscountRepository::class);
});

it('finds a single page by its state/city/business triple', function () {
    $local = LocalDiscount::factory()->create([
        'state' => 'texas',
        'city' => 'houston',
        'business_slug' => 'houston-zoo',
    ]);

    expect($this->repository->find('texas', 'houston', 'houston-zoo')?->is($local))->toBeTrue()
        ->and($this->repository->find('texas', 'houston', 'missing'))->toBeNull();
});

it('rolls up pages by state and by city, ordered by company', function () {
    LocalDiscount::factory()->create(['state' => 'texas', 'city' => 'houston', 'business_slug' => 'zoo', 'company' => 'Zoo']);
    LocalDiscount::factory()->create(['state' => 'texas', 'city' => 'houston', 'business_slug' => 'museum', 'company' => 'Museum']);
    LocalDiscount::factory()->create(['state' => 'texas', 'city' => 'austin', 'business_slug' => 'cafe', 'company' => 'Cafe']);
    LocalDiscount::factory()->create(['state' => 'ohio', 'city' => 'columbus', 'business_slug' => 'gym', 'company' => 'Gym']);

    expect($this->repository->forState('texas')->pluck('company')->all())->toBe(['Cafe', 'Museum', 'Zoo'])
        ->and($this->repository->forCity('texas', 'houston')->pluck('company')->all())->toBe(['Museum', 'Zoo']);
});
