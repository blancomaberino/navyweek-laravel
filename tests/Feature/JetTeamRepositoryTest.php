<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\TeamId;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use App\Domain\Pillars\Repositories\EloquentJetTeamRepository;
use App\Domain\Pillars\Repositories\JetTeamRepositoryInterface;

beforeEach(function () {
    $this->repository = app(JetTeamRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentJetTeamRepository::class);
});

it('finds a team by id and by base path (normalizing the leading slash)', function () {
    $team = JetTeam::factory()->create();

    expect($this->repository->findTeam(TeamId::BlueAngels)?->is($team))->toBeTrue()
        ->and($this->repository->findByBasePath('/blue-angels')?->is($team))->toBeTrue()
        ->and($this->repository->findByBasePath('blue-angels')?->is($team))->toBeTrue()
        ->and($this->repository->findByBasePath('/missing'))->toBeNull();
});

it('returns both team hubs', function () {
    JetTeam::factory()->create();
    JetTeam::factory()->thunderbirds()->create();

    expect($this->repository->allTeams()->pluck('team')->all())
        ->toBe([TeamId::BlueAngels, TeamId::Thunderbirds]);
});

it('returns a team schedule in authored order, and empty for an unknown team', function () {
    $team = JetTeam::factory()->create();
    JetTeamScheduleRow::factory()->for($team, 'team')->create(['sort_order' => 1, 'show' => 'Second']);
    JetTeamScheduleRow::factory()->for($team, 'team')->create(['sort_order' => 0, 'show' => 'First']);

    expect($this->repository->schedule(TeamId::BlueAngels)->pluck('show')->all())->toBe(['First', 'Second'])
        ->and($this->repository->schedule(TeamId::Thunderbirds))->toHaveCount(0);
});

it('returns only published city guides for a team, ordered by city', function () {
    $team = JetTeam::factory()->create();
    JetTeamCity::factory()->for($team, 'team')->create(['city' => 'Wasilla', 'slug' => 'wasilla']);
    JetTeamCity::factory()->for($team, 'team')->create(['city' => 'Anchorage', 'slug' => 'anchorage']);
    JetTeamCity::factory()->for($team, 'team')->unpublished()->create(['city' => 'Draft', 'slug' => 'draft']);

    expect($this->repository->publishedCities(TeamId::BlueAngels)->pluck('city')->all())
        ->toBe(['Anchorage', 'Wasilla']);
});

it('finds a single city guide by team and slug', function () {
    $team = JetTeam::factory()->create();
    $city = JetTeamCity::factory()->for($team, 'team')->create(['slug' => 'anchorage']);

    expect($this->repository->findCity(TeamId::BlueAngels, 'anchorage')?->is($city))->toBeTrue()
        ->and($this->repository->findCity(TeamId::BlueAngels, 'missing'))->toBeNull()
        ->and($this->repository->findCity(TeamId::Thunderbirds, 'anchorage'))->toBeNull();
});
