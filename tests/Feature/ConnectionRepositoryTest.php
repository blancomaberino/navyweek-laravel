<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Models\ConnectionAlias;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Crm\Repositories\EloquentConnectionRepository;

beforeEach(function () {
    $this->repository = app(ConnectionRepositoryInterface::class);
});

it('is bound to the Eloquent implementation', function () {
    expect($this->repository)
        ->toBeInstanceOf(EloquentConnectionRepository::class);
});

it('finds a connection by slug', function () {
    Connection::factory()->create(['slug' => 'apple']);

    expect($this->repository->findBySlug('apple'))->not->toBeNull()
        ->and($this->repository->findBySlug('missing'))->toBeNull();
});

it('resolves an alias slug to its canonical connection', function () {
    $canonical = Connection::factory()->create(['slug' => 'american-airlines']);
    ConnectionAlias::create(['alias_slug' => 'aa', 'connection_id' => $canonical->id]);

    expect($this->repository->findByAliasSlug('aa')?->is($canonical))->toBeTrue()
        ->and($this->repository->findByAliasSlug('unknown'))->toBeNull();
});

it('upserts idempotently keyed on slug', function () {
    $first = $this->repository->upsertBySlug('apple', [
        'brand' => 'Apple',
        'key' => 'apple',
        'max_volume' => 35_000,
    ]);

    $second = $this->repository->upsertBySlug('apple', [
        'brand' => 'Apple',
        'key' => 'apple',
        'max_volume' => 40_000,
    ]);

    expect($second->id)->toBe($first->id)
        ->and(Connection::count())->toBe(1)
        ->and($second->max_volume)->toBe(40_000);
});

it('returns only connections due for review as of a date', function () {
    Connection::factory()->dueForReview('2026-01-01')->create(['slug' => 'stale']);
    Connection::factory()->create(['slug' => 'future', 'next_review_due' => '2099-01-01']);
    Connection::factory()->create(['slug' => 'never', 'next_review_due' => null]);

    $due = $this->repository->dueForReview(new DateTimeImmutable('2026-07-24'));

    expect($due)->toHaveCount(1)
        ->and($due->first()->slug)->toBe('stale');
});

it('bulk-sets status only on the given live ids and returns the affected count', function () {
    $a = Connection::factory()->create(['status' => ConnectionStatus::Pending]);
    $b = Connection::factory()->create(['status' => ConnectionStatus::Pending]);
    $trashed = Connection::factory()->create(['status' => ConnectionStatus::Pending]);
    $trashed->delete(); // soft-deleted → excluded by the scope
    $untouched = Connection::factory()->create(['status' => ConnectionStatus::Pending]);

    $affected = $this->repository->updateStatusForIds(
        [$a->id, $b->id, $trashed->id],
        ConnectionStatus::Published,
    );

    // The count reflects only the rows actually changed (the two live ones), so the
    // UI can warn that the trashed selection was skipped rather than report success.
    expect($affected)->toBe(2)
        ->and($a->refresh()->status)->toBe(ConnectionStatus::Published)
        ->and($b->refresh()->status)->toBe(ConnectionStatus::Published)
        ->and(Connection::withTrashed()->findOrFail($trashed->id)->status)->toBe(ConnectionStatus::Pending)
        ->and($untouched->refresh()->status)->toBe(ConnectionStatus::Pending);
});
