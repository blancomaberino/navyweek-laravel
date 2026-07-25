<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\Placement;
use App\Domain\Catalog\Models\AffiliateLink;
use App\Domain\Catalog\Models\AffiliateNetwork;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use Database\Seeders\AffiliateNetworkSeeder;

it('casts the network extra_params to an array', function () {
    $network = AffiliateNetwork::factory()->direct()->create();

    expect($network->fresh()->extra_params)
        ->toBe(['utm_source' => 'navyweek', 'utm_medium' => 'referral']);
});

it('casts the link placement and resolves its relations', function () {
    $offer = Offer::factory()->create();
    $network = AffiliateNetwork::factory()->create();
    $link = AffiliateLink::factory()->create([
        'offer_id' => $offer->id,
        'affiliate_network_id' => $network->id,
        'placement' => Placement::StickyFooter,
    ]);

    $fresh = $link->fresh();

    expect($fresh->placement)->toBe(Placement::StickyFooter)
        ->and($fresh->offer->is($offer))->toBeTrue()
        ->and($fresh->network->is($network))->toBeTrue()
        ->and($fresh->rel)->toBe('sponsored noopener noreferrer');
});

it('lets a connection declare a default affiliate network', function () {
    $network = AffiliateNetwork::factory()->create();
    $connection = Connection::factory()->create(['default_affiliate_network_id' => $network->id]);

    expect($connection->defaultAffiliateNetwork->is($network))->toBeTrue();
});

it('cascades links when the offer is deleted', function () {
    $offer = Offer::factory()->create();
    AffiliateLink::factory()->create(['offer_id' => $offer->id]);

    $offer->delete();

    expect(AffiliateLink::count())->toBe(0);
});

it('seeds the seven networks from the legacy registry (idempotent)', function () {
    (new AffiliateNetworkSeeder)->run();
    (new AffiliateNetworkSeeder)->run();

    expect(AffiliateNetwork::count())->toBe(7)
        ->and(AffiliateNetwork::where('key', 'impact')->value('subid_param'))->toBe('subId1')
        ->and(AffiliateNetwork::where('key', 'direct')->value('subid_param'))->toBe('utm_content');
});
