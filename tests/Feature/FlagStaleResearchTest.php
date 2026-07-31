<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
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

it('never flags a published connection that has never been verified (null next_review_due)', function () {
    // A brand-new published connection has no cadence date yet. dueForReview relies on
    // `<= today` to exclude NULLs, so "never verified" must NOT count as stale.
    $neverVerified = Connection::factory()->published()->create([
        'last_verified_at' => null,
        'next_review_due' => null,
    ]);
    $brief = Research::factory()->create([
        'connection_id' => $neverVerified->id,
        'status' => ResearchStatus::Complete,
    ]);

    $this->artisan('research:flag-stale')->assertSuccessful();

    expect($neverVerified->refresh()->status)->toBe(ConnectionStatus::Published)
        ->and($brief->refresh()->status)->toBe(ResearchStatus::Complete);
});

it('leaves a mid-flight Draft brief untouched while still flagging the connection', function () {
    $due = Connection::factory()->published()->dueForReview('2020-01-01')->create();
    $draft = Research::factory()->create([
        'connection_id' => $due->id,
        'status' => ResearchStatus::Draft,
    ]);

    $this->artisan('research:flag-stale')->assertSuccessful();

    // Connection is surfaced, but the in-progress draft is not clobbered to Stale.
    expect($due->refresh()->status)->toBe(ConnectionStatus::NeedsReverify)
        ->and($draft->refresh()->status)->toBe(ResearchStatus::Draft);
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

it('markNeedsReverify only transitions an active connection (re-checked under lock)', function () {
    $repo = app(ConnectionRepositoryInterface::class);
    $active = Connection::factory()->published()->create();
    // Simulates a connection edited out of the active set after the batch read.
    $skipped = Connection::factory()->create(['status' => ConnectionStatus::Skipped]);

    expect($repo->markNeedsReverify($active))->toBeTrue()
        ->and($active->refresh()->status)->toBe(ConnectionStatus::NeedsReverify)
        ->and($repo->markNeedsReverify($skipped))->toBeFalse()
        ->and($skipped->refresh()->status)->toBe(ConnectionStatus::Skipped);
});
