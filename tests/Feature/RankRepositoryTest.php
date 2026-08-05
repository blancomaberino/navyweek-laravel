<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Models\Rank;
use App\Domain\Pillars\Repositories\EloquentRankRepository;
use App\Domain\Pillars\Repositories\RankRepositoryInterface;

beforeEach(function () {
    $this->repository = app(RankRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentRankRepository::class);
});

it('finds a rank by slug', function () {
    $rank = Rank::factory()->create(['slug' => 'ensign']);

    expect($this->repository->findBySlug('ensign')?->is($rank))->toBeTrue()
        ->and($this->repository->findBySlug('missing'))->toBeNull();
});

it('returns a category ordered by paygrade then name, scoped to the category', function () {
    Rank::factory()->create(['category' => RankCategory::OfficerCommissioned, 'paygrade' => 'O-3', 'name' => 'Lieutenant']);
    Rank::factory()->create(['category' => RankCategory::OfficerCommissioned, 'paygrade' => 'O-1', 'name' => 'Ensign']);
    // O-10 sorts lexically between O-1 and O-3 (documented grouping-order caveat —
    // the strict rank sequence is the next/previous linked list, not this read).
    Rank::factory()->create(['category' => RankCategory::OfficerCommissioned, 'paygrade' => 'O-10', 'name' => 'Fleet Admiral']);
    // A different category must not leak in.
    Rank::factory()->enlisted()->create(['name' => 'Chief Petty Officer']);

    $result = $this->repository->forCategory(RankCategory::OfficerCommissioned);

    expect($result->pluck('name')->all())->toBe(['Ensign', 'Fleet Admiral', 'Lieutenant']);
});

it('orders historic ratings by decommission year, keeping import order within a year', function () {
    // The legacy /navy-ratings/ hub stable-sorted the registry by year descending, so
    // ratings retired in the same year keep registry (= import = id) order, NOT
    // alphabetical order. Storekeeper is imported before Aviation Storekeeper.
    Rank::factory()->ratingHistorical()->create(['slug' => 'storekeeper', 'name' => 'Storekeeper', 'decommissioned_year' => 2009]);
    Rank::factory()->ratingHistorical()->create(['slug' => 'aviation-storekeeper', 'name' => 'Aviation Storekeeper', 'decommissioned_year' => 2009]);
    Rank::factory()->ratingHistorical()->create(['slug' => 'instrumentman', 'name' => 'Instrumentman', 'decommissioned_year' => 2007]);

    expect($this->repository->historicRatings()->pluck('slug')->all())
        ->toBe(['storekeeper', 'aviation-storekeeper', 'instrumentman']);
});
