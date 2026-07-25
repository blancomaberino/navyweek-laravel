<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Models\Research;
use App\Domain\Research\Repositories\EloquentResearchRepository;
use App\Domain\Research\Repositories\ResearchRepositoryInterface;

beforeEach(function () {
    $this->repository = app(ResearchRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentResearchRepository::class);
});

it('returns the latest brief by version for a connection', function () {
    $connection = Connection::factory()->create();
    Research::factory()->for($connection)->create(['version' => 1, 'executive_summary' => 'old']);
    Research::factory()->for($connection)->create(['version' => 3, 'executive_summary' => 'newest']);
    Research::factory()->for($connection)->create(['version' => 2, 'executive_summary' => 'middle']);
    // A different connection's brief must not leak in.
    Research::factory()->create(['version' => 9]);

    $latest = $this->repository->latestForConnection($connection->id);

    expect($latest?->version)->toBe(3)
        ->and($latest?->executive_summary)->toBe('newest');
});

it('returns null when a connection has no research', function () {
    $connection = Connection::factory()->create();

    expect($this->repository->latestForConnection($connection->id))->toBeNull();
});

it('returns the full version history newest first', function () {
    $connection = Connection::factory()->create();
    Research::factory()->for($connection)->create(['version' => 1]);
    Research::factory()->for($connection)->create(['version' => 2]);

    $history = $this->repository->historyForConnection($connection->id);

    expect($history)->toHaveCount(2)
        ->and($history->pluck('version')->all())->toBe([2, 1]);
});
