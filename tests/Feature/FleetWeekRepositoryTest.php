<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\FleetWeekSeason;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Repositories\EloquentFleetWeekRepository;
use App\Domain\Pillars\Repositories\FleetWeekRepositoryInterface;

beforeEach(function () {
    $this->repository = app(FleetWeekRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentFleetWeekRepository::class);
});

it('finds a city by slug', function () {
    $fw = FleetWeek::factory()->create(['slug' => 'san-francisco']);

    expect($this->repository->findBySlug('san-francisco')?->is($fw))->toBeTrue()
        ->and($this->repository->findBySlug('missing'))->toBeNull();
});

it('groups cities by season, ordered by city', function () {
    FleetWeek::factory()->create(['season' => FleetWeekSeason::Fall, 'city' => 'Seattle', 'slug' => 'seattle']);
    FleetWeek::factory()->create(['season' => FleetWeekSeason::Fall, 'city' => 'Portland', 'slug' => 'portland']);
    FleetWeek::factory()->create(['season' => FleetWeekSeason::Spring, 'city' => 'Miami', 'slug' => 'miami']);

    expect($this->repository->forSeason(FleetWeekSeason::Fall)->pluck('city')->all())->toBe(['Portland', 'Seattle'])
        // all() spans seasons, ordered by city.
        ->and($this->repository->all()->pluck('city')->all())->toBe(['Miami', 'Portland', 'Seattle']);
});
