<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Filament\Resources\Offers\RelationManagers\TiersRelationManager;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/** Create an offer under a fresh connection. */
function makeOffer(array $attributes = []): Offer
{
    $connection = Connection::factory()->create();

    return $connection->offers()->create(array_merge([
        'offer_type' => OfferType::Everyday,
        'internal_label' => $connection->brand.' — Everyday',
        'headline_discount' => '20% off',
        'is_primary' => true,
        'is_published' => true,
    ], $attributes));
}

it('lists offers with the brand and offer-type columns', function () {
    $everyday = makeOffer();
    $advisory = makeOffer(['offer_type' => OfferType::AdvisoryNoDiscount, 'is_primary' => false]);

    Livewire::test(ListOffers::class)
        ->assertCanSeeTableRecords([$everyday, $advisory])
        ->assertCanRenderTableColumn('connection.brand')
        ->assertCanRenderTableColumn('offer_type');
});

it('filters offers by type', function () {
    $everyday = makeOffer();
    $advisory = makeOffer(['offer_type' => OfferType::AdvisoryNoDiscount]);

    Livewire::test(ListOffers::class)
        ->filterTable('offer_type', OfferType::AdvisoryNoDiscount->value)
        ->assertCanSeeTableRecords([$advisory])
        ->assertCanNotSeeTableRecords([$everyday]);
});

it('edits an offer and persists the change', function () {
    $offer = makeOffer(['headline_discount' => 'Old']);

    Livewire::test(EditOffer::class, ['record' => $offer->getRouteKey()])
        ->fillForm(['headline_discount' => '25% off for veterans'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($offer->refresh()->headline_discount)->toBe('25% off for veterans');
});

it('manages savings tiers through the relation manager', function () {
    $offer = makeOffer();
    $offer->tiers()->create(['audience' => 'Active duty', 'amount' => '20% off', 'sort_order' => 0]);

    Livewire::test(TiersRelationManager::class, [
        'ownerRecord' => $offer,
        'pageClass' => EditOffer::class,
    ])->assertCanSeeTableRecords($offer->tiers()->get());
});
