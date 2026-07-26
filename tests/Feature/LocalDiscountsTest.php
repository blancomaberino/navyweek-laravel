<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\LocalVerification;
use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Models\LocalStore;
use App\Domain\Pillars\Models\UsState;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

it('casts the verification enum, audience booleans and JSON display lists', function () {
    $local = LocalDiscount::factory()->create([
        'tiers' => [['audience' => 'Active duty', 'amount' => 'Free']],
        'nearby_bases' => [['name' => 'Ellington Field', 'distanceMi' => 18]],
    ]);

    $fresh = $local->fresh();

    expect($fresh->verification)->toBe(LocalVerification::InStoreId)
        ->and($fresh->active_duty)->toBeTrue()
        ->and($fresh->veterans)->toBeFalse()
        ->and($fresh->tiers)->toBe([['audience' => 'Active duty', 'amount' => 'Free']])
        ->and($fresh->nearby_bases[0]['name'])->toBe('Ellington Field')
        ->and($fresh->date_published->toDateString())->toBe('2026-07-23');
});

it('derives hasAnyAudience from the five flags', function () {
    expect(LocalDiscount::factory()->create()->hasAnyAudience())->toBeTrue();

    $none = LocalDiscount::factory()->create([
        'active_duty' => false,
        'veterans' => false,
        'retirees' => false,
        'reserve_guard' => false,
        'military_family' => false,
    ]);
    expect($none->hasAnyAudience())->toBeFalse();
});

it('links to its U.S. state by slug against the shared lookup', function () {
    $state = UsState::factory()->create(['slug' => 'texas', 'name' => 'Texas', 'abbr' => 'TX']);
    $local = LocalDiscount::factory()->create(['state' => 'texas']);

    expect($local->usState->is($state))->toBeTrue();
});

it('nests stores and their opening hours in order', function () {
    $local = LocalDiscount::factory()->create();
    $primary = LocalStore::factory()->for($local)->create(['sort_order' => 0, 'name' => 'Primary']);
    LocalStore::factory()->for($local)->create(['sort_order' => 1, 'name' => 'Second']);
    $primary->hours()->createMany([
        ['days' => 'Sat', 'day_of_week' => ['Saturday'], 'opens' => '10:00', 'closes' => '18:00', 'sort_order' => 1],
        ['days' => 'Mon–Fri', 'day_of_week' => ['Monday'], 'opens' => '09:00', 'closes' => '17:00', 'sort_order' => 0],
    ]);

    expect($local->stores->pluck('name')->all())->toBe(['Primary', 'Second'])
        ->and($local->stores->first()->hours->pluck('days')->all())->toBe(['Mon–Fri', 'Sat'])
        // Inverse belongsTo both ways down the chain.
        ->and($primary->localDiscount->is($local))->toBeTrue()
        ->and($primary->hours->first()->localStore->is($primary))->toBeTrue();
});

it('attaches FAQs and sources via the shared polymorphic tables, in order', function () {
    $local = LocalDiscount::factory()->create();
    Faq::factory()->for($local, 'faqable')->create(['question' => 'B', 'sort_order' => 2]);
    Faq::factory()->for($local, 'faqable')->create(['question' => 'A', 'sort_order' => 1]);
    Source::factory()->for($local, 'sourceable')->create();

    expect($local->faqs->pluck('question')->all())->toBe(['A', 'B'])
        ->and($local->sources)->toHaveCount(1)
        ->and($local->faqs->first()->faqable->is($local))->toBeTrue();
});

it('exposes the verification display label', function () {
    expect(LocalVerification::ReservationId->label())->toBe('Reservation + ID');
});
