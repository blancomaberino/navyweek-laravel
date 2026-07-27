<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Pillars\Repositories\EloquentNavyWeekEventRepository;
use App\Domain\Pillars\Repositories\NavyWeekEventRepositoryInterface;

beforeEach(function () {
    $this->repository = app(NavyWeekEventRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentNavyWeekEventRepository::class);
});

it('finds a stop by slug', function () {
    $event = NavyWeekEvent::factory()->create(['slug' => 'rio-grande-valley']);

    expect($this->repository->findBySlug('rio-grande-valley')?->is($event))->toBeTrue()
        ->and($this->repository->findBySlug('missing'))->toBeNull();
});

it('returns all stops in canonical sequence order', function () {
    NavyWeekEvent::factory()->create(['sequence' => 3, 'slug' => 'third']);
    NavyWeekEvent::factory()->create(['sequence' => 1, 'slug' => 'first']);
    NavyWeekEvent::factory()->create(['sequence' => 2, 'slug' => 'second']);

    expect($this->repository->all()->pluck('slug')->all())->toBe(['first', 'second', 'third']);
});

it('filters by status, in sequence order', function () {
    NavyWeekEvent::factory()->completed()->create(['sequence' => 1, 'slug' => 'done-a']);
    NavyWeekEvent::factory()->create(['sequence' => 2, 'slug' => 'upcoming-b']);
    NavyWeekEvent::factory()->completed()->create(['sequence' => 3, 'slug' => 'done-c']);

    expect($this->repository->byStatus(NavyWeekStatus::Completed)->pluck('slug')->all())
        ->toBe(['done-a', 'done-c']);
});
