<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\VeteransDayMeal;
use App\Domain\Catalog\Repositories\EloquentVeteransDayMealRepository;
use App\Domain\Catalog\Repositories\VeteransDayMealRepositoryInterface;

beforeEach(function () {
    $this->repository = app(VeteransDayMealRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentVeteransDayMealRepository::class);
});

it('finds a meal by slug', function () {
    $meal = VeteransDayMeal::factory()->create(['slug' => 'applebees']);

    expect($this->repository->findBySlug('applebees')?->is($meal))->toBeTrue()
        ->and($this->repository->findBySlug('missing'))->toBeNull();
});

it('returns only verified, sourced offers, ordered by brand', function () {
    VeteransDayMeal::factory()->create(['brand' => 'Wendy’s', 'slug' => 'wendys']);
    VeteransDayMeal::factory()->create(['brand' => 'Applebee’s', 'slug' => 'applebees']);
    VeteransDayMeal::factory()->pending()->create(['brand' => 'Pending Co', 'slug' => 'pending']);
    VeteransDayMeal::factory()->discontinued()->create(['brand' => 'Gone Co', 'slug' => 'gone']);
    VeteransDayMeal::factory()->create(['brand' => 'No Source', 'slug' => 'no-source', 'source_url' => '']);

    $result = $this->repository->verified();

    expect($result->pluck('slug')->all())->toBe(['applebees', 'wendys']);
});
