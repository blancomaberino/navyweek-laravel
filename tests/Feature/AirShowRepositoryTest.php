<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Pillars\Repositories\AirShowRepositoryInterface;
use App\Domain\Pillars\Repositories\EloquentAirShowRepository;

beforeEach(function () {
    $this->repository = app(AirShowRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentAirShowRepository::class);
});

it('finds a show by slug', function () {
    $show = AirShow::factory()->create(['slug' => 'miramar']);

    expect($this->repository->findBySlug('miramar')?->is($show))->toBeTrue()
        ->and($this->repository->findBySlug('missing'))->toBeNull();
});

it('returns only published shows, ordered by start date with unconfirmed last', function () {
    // Deliberately inserted out of order; the repo sorts by start_date, forcing the
    // date-unconfirmed show last (the legacy airshows/index.ts list order).
    AirShow::factory()->create(['short_name' => 'Oceana', 'slug' => 'oceana', 'start_date' => '2026-09-26']);
    AirShow::factory()->create(['short_name' => 'Miramar', 'slug' => 'miramar', 'start_date' => '2026-05-01']);
    AirShow::factory()->unconfirmed()->create(['short_name' => 'TBD', 'slug' => 'tbd']);
    AirShow::factory()->unpublished()->create(['short_name' => 'Draft', 'slug' => 'draft']);

    // Miramar (May) → Oceana (Sep) → TBD (no date, last); Draft excluded (unpublished).
    expect($this->repository->published()->pluck('short_name')->all())
        ->toBe(['Miramar', 'Oceana', 'TBD']);
});

it('returns the single hub meta record', function () {
    expect($this->repository->hub())->toBeNull();

    $hub = AirShowHubMeta::factory()->create();

    expect($this->repository->hub()?->is($hub))->toBeTrue();
});
