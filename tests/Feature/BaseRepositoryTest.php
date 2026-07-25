<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\BaseType;
use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Repositories\BaseRepositoryInterface;
use App\Domain\Pillars\Repositories\EloquentBaseRepository;

beforeEach(function () {
    $this->repository = app(BaseRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentBaseRepository::class);
});

it('finds a base by slug', function () {
    $base = Base::factory()->create(['slug' => 'naval-station-norfolk']);

    expect($this->repository->findBySlug('naval-station-norfolk')?->is($base))->toBeTrue()
        ->and($this->repository->findBySlug('missing'))->toBeNull();
});

it('returns state bases ordered by name, scoped to the state slug', function () {
    Base::factory()->create(['state' => 'virginia', 'name' => 'Bravo Base']);
    Base::factory()->create(['state' => 'virginia', 'name' => 'Alpha Base']);
    Base::factory()->create(['state' => 'california', 'name' => 'Other Base']);

    $result = $this->repository->forState('virginia');

    expect($result->pluck('name')->all())->toBe(['Alpha Base', 'Bravo Base']);
});

it('returns overseas bases by country slug', function () {
    Base::factory()->overseas()->create(['country_slug' => 'japan', 'name' => 'Yokosuka']);
    Base::factory()->overseas()->create(['country_slug' => 'italy', 'name' => 'Naples']);

    expect($this->repository->forCountry('japan')->pluck('name')->all())->toBe(['Yokosuka']);
});

it('filters by installation type and by combatant command', function () {
    Base::factory()->create(['type' => BaseType::SubmarineBase, 'name' => 'Kings Bay']);
    Base::factory()->create(['type' => BaseType::NavalStation, 'name' => 'Norfolk']);
    Base::factory()->overseas()->create(['region' => CombatantCommand::Eucom, 'name' => 'Rota']);

    expect($this->repository->forType(BaseType::SubmarineBase)->pluck('name')->all())->toBe(['Kings Bay'])
        ->and($this->repository->forRegion(CombatantCommand::Eucom)->pluck('name')->all())->toBe(['Rota']);
});
