<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\EloquentOfferRepository;
use App\Domain\Catalog\Repositories\OfferRepositoryInterface;
use App\Domain\Crm\Models\Connection;

beforeEach(function () {
    $this->repository = app(OfferRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentOfferRepository::class);
});

it('returns a connection offers, primary first then by sort order', function () {
    $connection = Connection::factory()->create();
    Offer::factory()->for($connection)->create(['internal_label' => 'secondary b', 'is_primary' => false, 'sort_order' => 2]);
    Offer::factory()->for($connection)->create(['internal_label' => 'primary', 'is_primary' => true, 'sort_order' => 5]);
    Offer::factory()->for($connection)->create(['internal_label' => 'secondary a', 'is_primary' => false, 'sort_order' => 1]);
    // A different connection's offer must not leak in.
    Offer::factory()->create();

    $offers = $this->repository->forConnection($connection->id);

    expect($offers)->toHaveCount(3)
        ->and($offers->pluck('internal_label')->all())
        ->toBe(['primary', 'secondary a', 'secondary b']);
});

it('finds the primary offer for a connection', function () {
    $connection = Connection::factory()->create();
    Offer::factory()->for($connection)->create(['is_primary' => false]);
    $primary = Offer::factory()->for($connection)->create(['is_primary' => true]);

    expect($this->repository->primaryForConnection($connection->id)?->is($primary))->toBeTrue();
});

it('returns null when a connection has no primary offer', function () {
    $connection = Connection::factory()->create();
    Offer::factory()->for($connection)->create(['is_primary' => false]);

    expect($this->repository->primaryForConnection($connection->id))->toBeNull();
});
