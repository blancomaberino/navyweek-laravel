<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\Audience;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Models\ConnectionAlias;
use Illuminate\Database\UniqueConstraintViolationException;

it('casts status, audiences, cadence and dates', function () {
    $connection = Connection::factory()->create([
        'status' => ConnectionStatus::Published,
        'audiences' => [Audience::Military->value, Audience::Student->value],
        'cpc' => 3.5,
        'last_verified_at' => '2026-06-23',
    ]);

    $fresh = $connection->fresh();

    expect($fresh->status)->toBe(ConnectionStatus::Published)
        ->and($fresh->audiences)->toHaveCount(2)
        ->and($fresh->audiences->first())->toBe(Audience::Military)
        ->and($fresh->cpc)->toBe('3.50')
        ->and($fresh->research_cadence_days)->toBe(45)
        ->and($fresh->last_verified_at?->toDateString())->toBe('2026-06-23');
});

it('enforces a unique slug', function () {
    Connection::factory()->create(['slug' => 'apple']);

    expect(fn () => Connection::factory()->create(['slug' => 'apple']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('resolves a duplicate to its canonical connection', function () {
    $canonical = Connection::factory()->create(['slug' => 'american-airlines']);
    $duplicate = Connection::factory()->create([
        'slug' => 'aa',
        'status' => ConnectionStatus::Duplicate,
        'duplicate_of' => $canonical->id,
    ]);

    expect($duplicate->duplicateOf->is($canonical))->toBeTrue();
});

it('links keyword-variant aliases to a connection', function () {
    $connection = Connection::factory()->create(['slug' => 'american-airlines']);
    ConnectionAlias::create(['alias_slug' => 'aa', 'connection_id' => $connection->id]);

    expect($connection->aliases)->toHaveCount(1)
        ->and($connection->aliases->first()->alias_slug)->toBe('aa');
});

it('soft-deletes rather than hard-deleting', function () {
    $connection = Connection::factory()->create();
    $connection->delete();

    expect(Connection::count())->toBe(0)
        ->and(Connection::withTrashed()->count())->toBe(1);
});
