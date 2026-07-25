<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\AffiliateLink;
use App\Domain\Catalog\Models\AffiliateNetwork;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Repositories\AffiliateLinkRepositoryInterface;
use App\Domain\Catalog\Repositories\AffiliateNetworkRepositoryInterface;
use App\Domain\Catalog\Repositories\EloquentAffiliateLinkRepository;
use App\Domain\Catalog\Repositories\EloquentAffiliateNetworkRepository;

it('binds both affiliate repositories to their Eloquent implementations', function () {
    expect(app(AffiliateNetworkRepositoryInterface::class))->toBeInstanceOf(EloquentAffiliateNetworkRepository::class)
        ->and(app(AffiliateLinkRepositoryInterface::class))->toBeInstanceOf(EloquentAffiliateLinkRepository::class);
});

it('finds a network by its registry key', function () {
    AffiliateNetwork::factory()->create(['key' => 'rakuten']);
    $repository = app(AffiliateNetworkRepositoryInterface::class);

    expect($repository->findByKey('rakuten'))->not->toBeNull()
        ->and($repository->findByKey('missing'))->toBeNull();
});

it('returns an offer links with the network eager-loaded', function () {
    $offer = Offer::factory()->create();
    AffiliateLink::factory()->count(2)->create(['offer_id' => $offer->id]);
    AffiliateLink::factory()->create(); // a different offer's link

    $links = app(AffiliateLinkRepositoryInterface::class)->forOffer($offer->id);

    expect($links)->toHaveCount(2)
        ->and($links->first()->relationLoaded('network'))->toBeTrue();
});
