<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Research\Models\Research;

/** A connection with a published discount-brand page pointing at its primary offer. */
function connectionWithLivePage(array $attributes = []): Connection
{
    $connection = Connection::factory()->create(array_merge(['status' => ConnectionStatus::Published], $attributes));
    $offer = $connection->offers()->create([
        'offer_type' => OfferType::Everyday,
        'is_primary' => true,
        'is_published' => true,
    ]);
    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => "{$connection->slug}-military-veteran",
        'url_path' => "/discount/{$connection->slug}-military-veteran/",
        'title' => "{$connection->brand} Discount",
        'is_published' => true,
    ]);
    $page->pageable()->associate($offer)->save();

    return $connection;
}

it('passes when the pipeline state is consistent', function () {
    $connection = connectionWithLivePage();
    Research::factory()->create(['connection_id' => $connection->id]);

    $this->artisan('connections:reconcile', ['--check' => true])->assertSuccessful();
});

it('flags a published page with no research brief and fails --check (YMYL)', function () {
    connectionWithLivePage(); // published page, but no research row

    $this->artisan('connections:reconcile', ['--check' => true])
        ->expectsOutputToContain('YMYL')
        ->assertFailed();
});

it('flags a live page whose connection is not marked published', function () {
    $connection = connectionWithLivePage(['status' => ConnectionStatus::Pending]);
    Research::factory()->create(['connection_id' => $connection->id]);

    $this->artisan('connections:reconcile', ['--check' => true])
        ->expectsOutputToContain('not marked published')
        ->assertFailed();
});

it('does not flag a live page whose connection is a legitimately-marked duplicate', function () {
    // The `liveNotMarkedPublished` check excludes rows with `duplicate_of` set: a live
    // page on a connection that IS correctly marked Duplicate is intentional, not drift.
    $canonical = Connection::factory()->create();
    $dupe = connectionWithLivePage([
        'status' => ConnectionStatus::Duplicate,
        'duplicate_of' => $canonical->id,
    ]);
    Research::factory()->create(['connection_id' => $dupe->id]); // keep YMYL clean

    // The status-drift section reports "none" (the duplicate is excluded by the
    // whereNull('duplicate_of') guard); without it, this would be "✗ …: 1".
    $this->artisan('connections:reconcile', ['--check' => true])
        ->expectsOutputToContain('✓ Status drift — live page not marked published: none')
        ->assertSuccessful();
});

it('flags a duplicate that is not marked as a duplicate', function () {
    $canonical = Connection::factory()->create();
    Connection::factory()->create([
        'status' => ConnectionStatus::Pending,
        'duplicate_of' => $canonical->id,
    ]);

    $this->artisan('connections:reconcile', ['--check' => true])
        ->expectsOutputToContain('duplicate not marked duplicate')
        ->assertFailed();
});

it('reports without failing when --check is absent', function () {
    connectionWithLivePage(); // a YMYL drift exists

    // Report-only mode always exits 0 (drift is surfaced, not gated).
    $this->artisan('connections:reconcile')->assertSuccessful();
});
