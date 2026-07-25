<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Enums\RedemptionChannel;
use App\Domain\Catalog\Enums\VerificationProvider;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Models\OfferTier;
use App\Domain\Catalog\Models\RedemptionStep;
use App\Domain\Crm\Models\Connection;

it('casts enums, booleans and JSON display units', function () {
    $offer = Offer::factory()->create([
        'offer_type' => OfferType::Promo,
        'verification' => VerificationProvider::SheerId,
        'eligibility' => ['Active duty', 'Veterans'],
        'key_facts' => [['label' => 'Verification', 'value' => 'SheerID']],
    ]);

    $fresh = $offer->fresh();

    expect($fresh->offer_type)->toBe(OfferType::Promo)
        ->and($fresh->verification)->toBe(VerificationProvider::SheerId)
        ->and($fresh->is_primary)->toBeTrue()
        ->and($fresh->eligibility)->toBe(['Active duty', 'Veterans'])
        ->and($fresh->key_facts)->toBe([['label' => 'Verification', 'value' => 'SheerID']]);
});

it('leaves verification null on an advisory no-discount offer', function () {
    $offer = Offer::factory()->advisory()->create();

    expect($offer->fresh()->verification)->toBeNull()
        ->and($offer->offer_type)->toBe(OfferType::AdvisoryNoDiscount)
        ->and($offer->isAdvisoryNoDiscount())->toBeTrue();
});

it('belongs to a connection, which has many offers', function () {
    $connection = Connection::factory()->create();
    Offer::factory()->for($connection)->create();
    Offer::factory()->for($connection)->secondary()->create();

    expect($connection->offers)->toHaveCount(2)
        ->and($connection->offers->first()->connection->is($connection))->toBeTrue();
});

it('cascades child rows when an offer is deleted', function () {
    $offer = Offer::factory()->create();
    OfferTier::factory()->for($offer)->create();
    RedemptionStep::factory()->for($offer)->create();

    $offer->delete();

    expect(OfferTier::count())->toBe(0)
        ->and(RedemptionStep::count())->toBe(0);
});

it('orders tiers and redemption steps by sort_order', function () {
    $offer = Offer::factory()->create();
    OfferTier::factory()->for($offer)->create(['amount' => 'second', 'sort_order' => 2]);
    OfferTier::factory()->for($offer)->create(['amount' => 'first', 'sort_order' => 1]);
    RedemptionStep::factory()->for($offer)->inStore()->create(['title' => 'step two', 'sort_order' => 2]);
    RedemptionStep::factory()->for($offer)->create(['title' => 'step one', 'sort_order' => 1]);

    expect($offer->tiers->pluck('amount')->all())->toBe(['first', 'second'])
        ->and($offer->redemptionSteps->pluck('title')->all())->toBe(['step one', 'step two'])
        ->and($offer->redemptionSteps->first()->channel)->toBe(RedemptionChannel::Online);
});
