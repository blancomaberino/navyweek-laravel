<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Models\Research;

it('flags past-due active connections and marks their latest brief stale', function () {
    $due = Connection::factory()->published()->dueForReview('2020-01-01')->create();
    $latest = Research::factory()->create([
        'connection_id' => $due->id,
        'version' => 2,
        'status' => ResearchStatus::Complete,
    ]);
    Research::factory()->create(['connection_id' => $due->id, 'version' => 1, 'status' => ResearchStatus::Superseded]);

    $fresh = Connection::factory()->published()->dueForReview('2999-01-01')->create(); // not due

    $this->artisan('research:flag-stale')->assertSuccessful();

    expect($due->refresh()->status)->toBe(ConnectionStatus::NeedsReverify)
        ->and($latest->refresh()->status)->toBe(ResearchStatus::Stale)
        ->and($fresh->refresh()->status)->toBe(ConnectionStatus::Published);
});

it('leaves duplicate and skipped connections alone even when due', function () {
    $canonical = Connection::factory()->create();
    $duplicate = Connection::factory()->dueForReview('2020-01-01')->create([
        'status' => ConnectionStatus::Duplicate,
        'duplicate_of' => $canonical->id,
    ]);
    $skipped = Connection::factory()->dueForReview('2020-01-01')->create(['status' => ConnectionStatus::Skipped]);

    $this->artisan('research:flag-stale')->assertSuccessful();

    expect($duplicate->refresh()->status)->toBe(ConnectionStatus::Duplicate)
        ->and($skipped->refresh()->status)->toBe(ConnectionStatus::Skipped);
});

it('writes nothing in --dry-run', function () {
    $due = Connection::factory()->published()->dueForReview('2020-01-01')->create();

    $this->artisan('research:flag-stale', ['--dry-run' => true])->assertSuccessful();

    expect($due->refresh()->status)->toBe(ConnectionStatus::Published);
});
