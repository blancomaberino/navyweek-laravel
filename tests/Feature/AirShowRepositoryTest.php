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

it('returns only published shows, ordered by short name', function () {
    AirShow::factory()->create(['short_name' => 'Oceana', 'slug' => 'oceana']);
    AirShow::factory()->create(['short_name' => 'Andrews', 'slug' => 'andrews']);
    AirShow::factory()->unpublished()->create(['short_name' => 'Draft', 'slug' => 'draft']);

    expect($this->repository->published()->pluck('short_name')->all())->toBe(['Andrews', 'Oceana']);
});

it('returns the single hub meta record', function () {
    expect($this->repository->hub())->toBeNull();

    $hub = AirShowHubMeta::factory()->create();

    expect($this->repository->hub()?->is($hub))->toBeTrue();
});
